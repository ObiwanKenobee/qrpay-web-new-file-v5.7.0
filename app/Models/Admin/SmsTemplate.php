<?php

namespace App\Models\Admin;

use App\Constants\SmsTemplateConst;
use App\Providers\Admin\BasicSettingsProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsTemplate extends Model
{
    use HasFactory;
    protected $guarded = ['id'];

    protected $table = 'sms_templates';

    protected $casts = [
        'shortcodes'    => 'object',
        'last_edit_by'  => 'integer',
    ];

    public function admin() {
        return $this->belongsTo(Admin::class, 'last_edit_by');
    }

    // verification codes / credentials are always sent
    public function getAuthAttribute() {
        return !SmsTemplateConst::canDisable((string) $this->act);
    }

    public function getTypeAttribute() {
        return SmsTemplateConst::config((string) $this->act)['type'];
    }

    /**
     * Active template of an SMS type for the receiver's account type (user/agent/merchant).
     * Falls back to the user template, so a missing account copy still sends like before.
     * @param string $act
     * @param mixed $receiver user/agent/merchant model, null for mobile-only receivers (uses current panel)
     * @return self|null null when disabled or not found
     */
    public static function forReceiver(string $act, mixed $receiver = null): ?self
    {
        $group = EmailTemplate::accountGroup($receiver);
        $templates = self::where('act', $act)->whereIn('group', array_unique([$group, SmsTemplateConst::GROUP_USER]))->get();
        $template = $templates->firstWhere('group', $group) ?? $templates->firstWhere('group', SmsTemplateConst::GROUP_USER);

        return ($template && $template->sms_status == 1) ? $template : null;
    }

    /**
     * Replace placeholders. The required value (code/password) is added if admin removed its placeholder.
     * @param array $values placeholder => value
     * @return string
     */
    public function render(array $values): string
    {
        $values = array_merge([
            'site_name' => BasicSettingsProvider::get()->site_name ?? "",
        ], $values);

        $body = (string) $this->sms_body;
        $required = SmsTemplateConst::required((string) $this->act);
        if($required && !str_contains($body, '{{'.$required.'}}')) {
            $body = trim($body).' {{'.$required.'}}';
        }

        return trim(str_replace(
            array_map(fn($key) => '{{'.$key.'}}', array_keys($values)),
            array_map(fn($value) => is_scalar($value) || $value === null ? (string) $value : json_encode($value), array_values($values)),
            $body,
        ));
    }

    /**
     * SMS parts this text costs (GSM 160/153, unicode 70/67 characters per part)
     * @param string $text
     * @return int
     */
    public static function segments(string $text): int
    {
        $length = mb_strlen($text);
        if($length == 0) return 0;
        $unicode = preg_match('/[^\x00-\x7F£¥èéùìòÇØøÅå€]/u', $text);
        [$single, $multi] = $unicode ? [70, 67] : [160, 153];
        return $length <= $single ? 1 : (int) ceil($length / $multi);
    }
}
