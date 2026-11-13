<?php

namespace App\Http\Helpers;

/**
 * Developer documentation helpers.
 *  - nav():      single source for the sidebar + previous/next pager.
 *  - request():  builds the same API call in several languages.
 *
 * Code samples use placeholders that the browser swaps for the selected
 * environment (sandbox / production): __BASE_URL__, __HOST__, __BASE_PATH__.
 */
class DeveloperDocs
{
    public const LANGUAGES = [
        'curl'    => ['label' => 'cURL',          'hl' => 'bash'],
        'http'    => ['label' => 'HTTP',          'hl' => 'http'],
        'php'     => ['label' => 'PHP cURL',      'hl' => 'php'],
        'guzzle'  => ['label' => 'PHP Guzzle',    'hl' => 'php'],
        'laravel' => ['label' => 'Laravel HTTP',  'hl' => 'php'],
        'node'    => ['label' => 'Node.js',       'hl' => 'javascript'],
        'python'  => ['label' => 'Python',        'hl' => 'python'],
    ];

    public static function nav(): array
    {
        return [
            [
                'title' => 'Getting Started',
                'items' => [
                    ['route' => 'developer.index',          'title' => 'Introduction',   'icon' => 'las la-book-open'],
                    ['route' => 'developer.prerequisites',  'title' => 'Prerequisites',  'icon' => 'las la-clipboard-check'],
                    ['route' => 'developer.authentication', 'title' => 'Authentication', 'icon' => 'las la-key'],
                    ['route' => 'developer.base.url',       'title' => 'Base URL',       'icon' => 'las la-link'],
                ],
            ],
            [
                'title' => 'API Reference',
                'items' => [
                    ['route' => 'developer.access.token',         'title' => 'Access Token',         'method' => 'POST'],
                    ['route' => 'developer.initiate.payment',     'title' => 'Initiate Payment',     'method' => 'POST'],
                    ['route' => 'developer.check.status.payment', 'title' => 'Check Payment Status', 'method' => 'GET'],
                ],
            ],
            [
                'title' => 'Resources',
                'items' => [
                    ['route' => 'developer.response.code',  'title' => 'Response Codes', 'icon' => 'las la-list-ol'],
                    ['route' => 'developer.error.handling', 'title' => 'Error Handling', 'icon' => 'las la-exclamation-triangle'],
                    ['route' => 'developer.best.practices', 'title' => 'Best Practices', 'icon' => 'las la-shield-alt'],
                    ['route' => 'developer.examples',       'title' => 'Examples',       'icon' => 'las la-code'],
                    ['route' => 'developer.faq',            'title' => 'FAQ',            'icon' => 'las la-question-circle'],
                    ['route' => 'developer.support',        'title' => 'Support',        'icon' => 'las la-headset'],
                ],
            ],
        ];
    }

    /** Previous / next page for the current route. */
    public static function pager(?string $current): array
    {
        $flat = collect(self::nav())->pluck('items')->flatten(1)->values();
        $index = $flat->search(fn($item) => $item['route'] === $current);
        if ($index === false) return [null, null];

        return [$flat->get($index - 1), $flat->get($index + 1)];
    }

    /** Base URLs for both environments. */
    public static function environments(): array
    {
        $host = request()->getHttpHost();
        $root = rtrim(url('/'), '/');
        $path = trim(parse_url($root, PHP_URL_PATH) ?? '', '/');
        $path = $path ? '/' . $path : '';

        return [
            'sandbox'    => ['url' => $root . '/pay/sandbox/api/v1', 'host' => $host, 'path' => $path . '/pay/sandbox/api/v1'],
            'production' => ['url' => $root . '/pay/api/v1',         'host' => $host, 'path' => $path . '/pay/api/v1'],
        ];
    }

    /**
     * Same JSON request in every language.
     * @return array<string, array{label:string, hl:string, code:string}>
     */
    public static function request(string $method, string $endpoint, array $body = [], ?string $token = null): array
    {
        $method   = strtoupper($method);
        $url      = '__BASE_URL__' . $endpoint;
        $headers  = array_filter([
            'Authorization' => $token ? 'Bearer ' . $token : null,
            'Accept'        => 'application/json',
            'Content-Type'  => 'application/json',
        ]);
        $json     = json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $code = [];

        // cURL
        $lines = ["curl --request {$method} \\", "  --url '{$url}' \\"];
        foreach ($headers as $key => $value) $lines[] = "  --header '{$key}: {$value}' \\";
        $lines[] = "  --data '" . $json . "'";
        $code['curl'] = implode("\n", $lines);

        // Raw HTTP
        $lines = ["{$method} __BASE_PATH__{$endpoint} HTTP/1.1", 'Host: __HOST__'];
        foreach ($headers as $key => $value) $lines[] = "{$key}: {$value}";
        $code['http'] = implode("\n", $lines) . "\n\n" . $json;

        // PHP cURL
        $phpBody    = self::phpArray($body, 0);
        $phpHeaders = collect($headers)->map(fn($v, $k) => "        '{$k}: {$v}',")->implode("\n");
        $code['php'] = <<<PHP
<?php

\$payload = {$phpBody};

\$curl = curl_init();
curl_setopt_array(\$curl, [
    CURLOPT_URL            => '{$url}',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST  => '{$method}',
    CURLOPT_POSTFIELDS     => json_encode(\$payload),
    CURLOPT_HTTPHEADER     => [
{$phpHeaders}
    ],
]);

\$response = curl_exec(\$curl);
curl_close(\$curl);

\$result = json_decode(\$response, true);
print_r(\$result);
PHP;

        // Guzzle
        $guzzleHeaders = collect($headers)->map(fn($v, $k) => "        '{$k}' => '{$v}',")->implode("\n");
        $guzzleBody    = self::phpArray($body, 1);
        $code['guzzle'] = <<<PHP
<?php

require_once 'vendor/autoload.php';

\$client = new \GuzzleHttp\Client();

\$response = \$client->request('{$method}', '{$url}', [
    'json'    => {$guzzleBody},
    'headers' => [
{$guzzleHeaders}
    ],
]);

echo \$response->getBody();
PHP;

        // Laravel HTTP client
        $laravelBody = self::phpArray($body, 1);
        $auth        = $token ? "\n    ->withToken('{$token}')" : '';
        $lower       = strtolower($method);
        $code['laravel'] = <<<PHP
use Illuminate\Support\Facades\Http;

\$response = Http::acceptJson(){$auth}
    ->{$lower}('{$url}', {$laravelBody});

if (\$response->successful()) {
    \$data = \$response->json('data');
} else {
    \$errors = \$response->json('message.error');
}
PHP;

        // Node.js (fetch, Node 18+)
        $nodeHeaders = collect($headers)->map(fn($v, $k) => "    '{$k}': '{$v}',")->implode("\n");
        $nodeBody    = self::indent(preg_replace_callback('/^( +)/m', fn($m) => str_repeat(' ', intdiv(strlen($m[1]), 2)), $json), 2, false);
        $code['node'] = <<<JS
const response = await fetch('{$url}', {
  method: '{$method}',
  headers: {
{$nodeHeaders}
  },
  body: JSON.stringify({$nodeBody}),
});

const result = await response.json();
console.log(result);
JS;

        // Python (requests)
        $pyHeaders = collect($headers)->map(fn($v, $k) => "    \"{$k}\": \"{$v}\",")->implode("\n");
        $pyBody    = self::pythonDict($body);
        $code['python'] = <<<PY
import requests

payload = {$pyBody}

headers = {
{$pyHeaders}
}

response = requests.request("{$method}", "{$url}", json=payload, headers=headers)
print(response.json())
PY;

        return self::wrap($code);
    }

    /** Attach label + highlight language to a [key => code] list. */
    public static function wrap(array $code, array $labels = []): array
    {
        $out = [];
        foreach ($code as $key => $snippet) {
            $meta = self::LANGUAGES[$key] ?? ['label' => ucfirst($key), 'hl' => 'plaintext'];
            if (isset($labels[$key])) $meta['label'] = $labels[$key];
            $out[$key] = $meta + ['code' => $snippet];
        }
        return $out;
    }

    public static function json($data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected static function phpArray(array $data, int $level): string
    {
        if (empty($data)) return '[]';
        $pad   = str_repeat('    ', $level + 1);
        $width = max(array_map('strlen', array_keys($data))) + 2;
        $rows  = [];
        foreach ($data as $key => $value) {
            $rows[] = $pad . str_pad("'{$key}'", $width) . ' => ' . var_export($value, true) . ',';
        }
        return "[\n" . implode("\n", $rows) . "\n" . str_repeat('    ', $level) . ']';
    }

    protected static function pythonDict(array $data): string
    {
        if (empty($data)) return '{}';
        $rows = [];
        foreach ($data as $key => $value) {
            $rows[] = '    "' . $key . '": ' . json_encode($value, JSON_UNESCAPED_SLASHES) . ',';
        }
        return "{\n" . implode("\n", $rows) . "\n}";
    }

    protected static function indent(string $text, int $spaces, bool $first = true): string
    {
        $pad   = str_repeat(' ', $spaces);
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if ($i === 0 && !$first) continue;
            $lines[$i] = $pad . $line;
        }
        return implode("\n", $lines);
    }
}
