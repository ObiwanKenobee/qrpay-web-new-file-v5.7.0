<?php

namespace App\Models\Admin;

use App\Constants\EmailTemplateConst;
use App\Models\Agent;
use App\Models\Merchants\Merchant;
use App\Models\User;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    // only these files are attached inline to emails
    const EMBED_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'];

    protected $casts = [
        'id'            => 'integer',
        'slug'          => 'string',
        'type'          => 'string',
        'subject'       => 'string',
        'body'          => 'string',
        'status'        => 'boolean',
        'last_edit_by'  => 'integer',
    ];

    public function admin() {
        return $this->belongsTo(Admin::class, 'last_edit_by');
    }

    public function scopeActive($query) {
        return $query->where('status', true);
    }

    public function getGroupAttribute() {
        return EmailTemplateConst::config($this->slug)['group'];
    }

    // auth emails (OTP/reset link) are always sent
    public function getAuthAttribute() {
        return EmailTemplateConst::config($this->slug)['auth'] ?? false;
    }

    /**
     * Build mail message from an active template
     * @param string $slug
     * @param array $values placeholder => value (html allowed, escape user input before passing)
     * @param mixed $notifiable receiver; picks the user/agent/merchant version of the template
     * @return MailMessage|null null when template not found/disabled, caller should use default mail
     */
    public static function mailMessage(string $slug, array $values, mixed $notifiable = null): ?MailMessage
    {
        $email_template = self::where('slug', self::slugFor($slug, $notifiable))->active()->first();
        if(!$email_template) return null;

        $email = $email_template->render($values);
        return (new MailMessage)
                    ->subject($email['subject'])
                    ->view('mail-templates.email-template', [
                        'email_subject' => $email['subject'],
                        'email_body'    => $email['body'],
                    ]);
    }

    /**
     * Image Assets -> Logo (Light Version) column of each template owner
     */
    const LOGO_COLUMNS = [
        EmailTemplateConst::GROUP_ADMIN     => 'site_logo',
        EmailTemplateConst::GROUP_USER      => 'site_logo',
        EmailTemplateConst::GROUP_AGENT     => 'agent_site_logo',
        EmailTemplateConst::GROUP_MERCHANT  => 'merchant_site_logo',
    ];

    /**
     * Current logo (Light Version) of user/agent/merchant panel, falls back to the main site logo
     * @param string $group admin|user|agent|merchant
     * @return string
     */
    public static function logoUrl(string $group = EmailTemplateConst::GROUP_USER): string
    {
        $basic_settings = BasicSettingsProvider::get();
        $logo = $basic_settings->{self::LOGO_COLUMNS[$group] ?? 'site_logo'} ?: $basic_settings->site_logo;
        return $logo ? files_asset_path('image-assets').'/'.$logo : files_asset_path('default');
    }

    /**
     * Any image from the Image Assets folder (old/seeded/inserted logo) shows the current logo of the template owner,
     * so changing the logo from Image Assets updates every template.
     * @param string $html
     * @param string $group admin|user|agent|merchant
     * @return string
     */
    public static function syncLogo(string $html, string $group = EmailTemplateConst::GROUP_USER): string
    {
        $base = rtrim(files_asset_path('image-assets'), '/').'/';
        $logo = self::logoUrl($group);

        return preg_replace_callback('/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i', function($match) use ($base, $logo) {
            return str_starts_with(html_entity_decode($match[2]), $base) ? $match[1].e($logo).$match[3] : $match[0];
        }, $html);
    }

    /**
     * Logo opens the website when clicked in the mail box (logo already inside a link is kept as it is)
     * @param string $html
     * @return string
     */
    public static function linkLogo(string $html): string
    {
        $base = rtrim(files_asset_path('image-assets'), '/').'/';
        $site_url = e(url('/'));

        return preg_replace_callback('/(<a\b[^>]*>\s*)?(<img\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>)(\s*<\/a>)?/i', function($match) use ($base, $site_url) {
            if(!empty($match[1]) || !str_starts_with(html_entity_decode($match[3]), $base)) return $match[0];
            return '<a href="'.$site_url.'" target="_blank" rel="noopener">'.$match[2].'</a>'.($match[4] ?? '');
        }, $html);
    }

    /**
     * Embed this site's images (logo, editor images) into the mail as inline attachments,
     * so they show without "load remote images" and work even when the site is not public.
     * Images from other domains are left as links.
     * @param string $html
     * @param mixed $message \Illuminate\Mail\Message while sending, null otherwise
     * @return string
     */
    public static function embedImages(string $html, mixed $message): string
    {
        if(!$message instanceof \Illuminate\Mail\Message) return $html;

        // site url prefix => file contents reader (storage disk may be local or remote)
        $sources = [
            rtrim(Storage::url(''), '/').'/'    => fn($path) => Storage::exists($path) ? Storage::get($path) : null,
            rtrim(asset(''), '/').'/'           => function($path) {
                // only files inside public/ (blocks ../.env, /etc/passwd ...)
                $public = realpath(public_path());
                $file   = realpath(public_path($path));
                if(!$public || !$file || !str_starts_with($file, $public.DIRECTORY_SEPARATOR) || !is_file($file)) return null;
                return file_get_contents($file);
            },
        ];

        $embedded = [];
        return preg_replace_callback('/(<img\b[^>]*\bsrc=["\'])([^"\']+)(["\'])/i', function($match) use ($message, $sources, &$embedded) {
            $src = html_entity_decode($match[2]);
            foreach($sources as $base => $read) {
                if(!str_starts_with($src, $base)) continue;
                try {
                    if(!isset($embedded[$src])) {
                        $path = urldecode(strtok(substr($src, strlen($base)), '?'));
                        // images only, no path traversal
                        if(str_contains($path, '..') || str_contains($path, "\0")) break;
                        if(!in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::EMBED_IMAGE_EXTENSIONS)) break;
                        $contents = $read($path);
                        if(!$contents) break;
                        $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: '';
                        if(!str_starts_with($mime, 'image/')) break;
                        $embedded[$src] = $message->embedData($contents, basename($path), $mime);
                    }
                    return $match[1].$embedded[$src].$match[3];
                }catch(\Throwable $e) {
                    break;
                }
            }
            return $match[0];
        }, $html);
    }

    /**
     * Template disabled from admin panel (missing template is not disabled, default mail is used)
     * @param string $slug
     * @param mixed $notifiable receiver; picks the user/agent/merchant version of the template
     * @return bool
     */
    public static function isDisabled(string $slug, mixed $notifiable = null): bool
    {
        return self::where('slug', self::slugFor($slug, $notifiable))->where('status', false)->exists();
    }

    /**
     * Template slug for the receiver (user/agent/merchant), admin templates stay as they are
     * @param string $slug
     * @param mixed $notifiable
     * @return string
     */
    public static function slugFor(string $slug, mixed $notifiable = null): string
    {
        if($notifiable === null || $notifiable instanceof Admin) return $slug;
        return EmailTemplateConst::slugFor($slug, self::accountGroup($notifiable));
    }

    /**
     * Account type of the receiver: user, agent or merchant.
     * Email only receivers (register verification code) use the current panel.
     * @param mixed $notifiable
     * @return string
     */
    public static function accountGroup(mixed $notifiable): string
    {
        if($notifiable instanceof Agent) return EmailTemplateConst::GROUP_AGENT;
        if($notifiable instanceof Merchant) return EmailTemplateConst::GROUP_MERCHANT;
        if($notifiable instanceof User) return EmailTemplateConst::GROUP_USER;

        // web: agent/..., merchant/... | api: agent-api/agent/..., merchant-api/merchant/...
        $segments = array_slice(explode('/', request()->path()), 0, 2);
        if(array_intersect($segments, ['agent', 'agent-api'])) return EmailTemplateConst::GROUP_AGENT;
        if(array_intersect($segments, ['merchant', 'merchant-api'])) return EmailTemplateConst::GROUP_MERCHANT;
        return EmailTemplateConst::GROUP_USER;
    }

    /**
     * Build the fixed transaction fields table
     * @param string|array $content "Label : Value<br>Label : Value" html string or ['Label' => 'Value'] (escaped)
     * @return string
     */
    public static function detailsTable(string|array $content): string
    {
        $lines = [];
        if(is_array($content)) {
            foreach($content as $label => $value) {
                $lines[] = [e($label), e($value)];
            }
        }else {
            foreach(preg_split('/<br\s*\/?>/i', $content) as $line) {
                $line = trim($line);
                if($line === "") continue;
                // values come from user input (names, bill numbers...), escape like the array branch (keep existing entities)
                $line = e(html_entity_decode(strip_tags($line), ENT_QUOTES), false);
                $parts = explode(" : ", $line, 2);
                // "Label: value" (no space before the colon) is also a field, when the label is short
                if(count($parts) == 1 && preg_match('/^([^:]{1,40}):\s+(.+)$/u', $line, $match)) {
                    $parts = [$match[1], $match[2]];
                }
                $lines[] = count($parts) == 2 ? [trim($parts[0]), trim($parts[1])] : [$line];
            }
        }

        $rows = "";
        foreach($lines as $line) {
            if(count($line) == 2) {
                $rows .= '<tr><td style="padding:11px 16px;border-bottom:1px solid #ececf3;color:#6b7280;width:45%;">'.$line[0].'</td><td style="padding:11px 16px;border-bottom:1px solid #ececf3;color:#111827;font-weight:600;text-align:right;">'.$line[1].'</td></tr>';
            }else {
                $rows .= '<tr><td colspan="2" style="padding:11px 16px;border-bottom:1px solid #ececf3;color:#374151;">'.$line[0].'</td></tr>';
            }
        }

        return '<table role="presentation" style="width:100%;border-collapse:separate;border-spacing:0;font-size:14px;margin:4px 0 16px;background-color:#fafafc;border:1px solid #ececf3;border-radius:8px;overflow:hidden;">'.$rows.'</table>';
    }

    /**
     * Replace template placeholders
     * @param array $values placeholder => value
     * @return array ['subject','body']
     */
    public function render(array $values): array
    {
        $basic_settings = BasicSettingsProvider::get();

        $values = array_merge([
            'logo'          => '<img src="'.e(self::logoUrl($this->group)).'" alt="Logo" style="max-width:150px;width:auto;height:auto;">',
            'site_name'     => e($basic_settings->site_name ?? ""),
            'site_url'      => e(url('/')),
            'current_year'  => date('Y'),
        ], $values);

        // editor may url-encode placeholders inside links (href="{{reset_url}}")
        $body = self::syncLogo(str_replace(['%7B%7B', '%7D%7D'], ['{{', '}}'], $this->body), $this->group);
        $body = str_replace('{{logo}}', $values['logo'], $body);
        $body = self::linkLogo($body);
        // Required value (fields/code/link) must reach the receiver even if admin removed the placeholder
        $required = EmailTemplateConst::required($this->slug);
        if(!str_contains($body, '{{'.$required.'}}')) {
            $body .= '<p>{{'.$required.'}}</p>';
        }
        // table inside <p> is invalid html
        $body = str_replace('<p>{{details}}</p>', '{{details}}', $body);

        $body = str_replace(
            array_map(fn($key) => '{{'.$key.'}}', array_keys($values)),
            array_values($values),
            $body,
        );

        $subject_values = $values;
        unset($subject_values['logo'], $subject_values['details']);
        $subject = str_replace(
            array_map(fn($key) => '{{'.$key.'}}', array_keys($subject_values)),
            array_values($subject_values),
            $this->subject,
        );

        return [
            'subject'   => html_entity_decode(strip_tags($subject), ENT_QUOTES),
            'body'      => self::styleBody($body),
        ];
    }

    /**
     * Email look (spacing, typography, badge, note box) as inline styles.
     * Saved body stays simple editor (CKEditor) markup, design is added while sending/preview.
     * Existing inline styles of the template win over these defaults.
     * @param string $html
     * @return string
     */
    public static function styleBody(string $html): string
    {
        // empty lines (e.g. {{title}} without value)
        $html = preg_replace('#<p\b[^>]*>(\s|&nbsp;|<span\b[^>]*>\s*</span>)*</p>#i', '', $html);

        $styles = [
            'h2'            => 'margin:0 0 14px;font-size:22px;line-height:1.35;font-weight:700;color:#111827;',
            'h3'            => 'margin:24px 0 10px;font-size:17px;line-height:1.4;font-weight:700;color:#111827;',
            'h4'            => 'margin:24px 0 8px;font-size:15px;line-height:1.4;font-weight:700;color:#111827;',
            'p'             => 'margin:0 0 14px;font-size:15px;line-height:1.65;color:#374151;',
            'ul'            => 'margin:0 0 16px;padding-left:22px;color:#374151;',
            'ol'            => 'margin:0 0 16px;padding-left:22px;color:#374151;',
            'li'            => 'margin:0 0 6px;font-size:15px;line-height:1.6;',
            'blockquote'    => 'margin:18px 0;padding:14px 18px;background-color:#f6f5fa;border-left:4px solid #5a5278;border-radius:6px;color:#374151;',
            'hr'            => 'border:0;border-top:1px solid #e5e7eb;margin:28px 0 18px;',
            'a'             => 'color:#5a5278;text-decoration:none;',
        ];
        foreach($styles as $tag => $style) {
            $html = preg_replace_callback('#<('.$tag.')\b([^>]*?)(\s*/)?>#i', function($match) use ($style) {
                $attributes = $match[2];
                if(preg_match('#\bstyle=(["\'])(.*?)\1#is', $attributes, $existing)) {
                    $attributes = str_replace($existing[0], 'style="'.$style.$existing[2].'"', $attributes);
                }else {
                    $attributes .= ' style="'.$style.'"';
                }
                return '<'.$match[1].$attributes.($match[3] ?? '').'>';
            }, $html);
        }
        // text inside the note box has no extra bottom space
        $html = preg_replace('#(<blockquote\b[^>]*>\s*<p style=")margin:0 0 14px;#i', '$1margin:0;', $html);
        // colored background text (status badge / code) looks like a pill
        $html = preg_replace('#<span style="(background-color:[^"]*)"#i', '<span style="display:inline-block;padding:6px 16px;border-radius:999px;font-size:13px;letter-spacing:0.6px;$1"', $html);
        // verification code: bigger pill
        $html = preg_replace('#(<h2\b[^>]*>\s*<span style="display:inline-block;padding:6px 16px;border-radius:999px;font-size:13px;letter-spacing:0.6px;)#i', '$1padding:10px 26px;font-size:28px;letter-spacing:6px;border-radius:10px;', $html);
        return $html;
    }
}
