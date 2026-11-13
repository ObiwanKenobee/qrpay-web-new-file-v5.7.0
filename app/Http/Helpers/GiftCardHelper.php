<?php

namespace App\Http\Helpers;

use App\Constants\GlobalConst;
use App\Exceptions\GiftCardProviderException;
use App\Models\Admin\ReloadlyApi;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class GiftCardHelper {

    /**
     * Active API
     */
    public $api;

    /**
     * Store access token
     */
    protected $access_token;

    /**
     * store configuration
     */
    protected array $config = [];

    /**
     * Country Cache KEY
     */
    const COUNTRIES_CACHE_KEY = "gift_card_api_countries_{provider}_{env}";

    /**
     * Product search cache key prefix (one entry per search params)
     */
    const SEARCH_CACHE_KEY = "gift_card_api_search_{provider}_{env}_";

    /**
     * Product search cache lifetime (seconds)
     */
    const SEARCH_CACHE_TTL = 600;

    /**
     * Provider page size limit
     */
    const MAX_PAGE_SIZE = 200;

    /**
     * Safe user-facing messages
     */
    const MSG_UNAVAILABLE   = "Gift card service is currently unavailable. Please try again later.";
    const MSG_NOT_FOUND     = "Gift card not found.";
    const MSG_NO_COUNTRY    = "No gift cards found for this country.";
    const MSG_ORDER_FAILED  = "Your gift card order could not be placed. Please try again later.";

    /**
     * API Status
     */
    const STATUS_SUCCESS    = "SUCCESSFUL";
    const STATUS_PENDING    = "PENDING";
    const STATUS_PROCESSING = "PROCESSING";
    const STATUS_REFUNDED   = "REFUNDED";
    const STATUS_FAILED     = "FAILED";

    /**
     * Price Types
     */
    const PRICE_TYPES = [
        "FIXED" => "FIXED",
        "RANGE" => "RANGE",
    ];

    public function __construct()
    {
        $this->api = ReloadlyApi::reloadly()->giftCard()->first();
        $this->setConfig();
        $this->accessToken();
    }

    /**
     * Set configuration
     */
    public function setConfig()
    {
        $api = $this->api;

        if(!$api) $this->fail('config', ['message' => 'Gift card provider is not configured']);

        $config['client_id']    = $api->credentials?->client_id;
        $config['secret_key']   = $api->credentials?->secret_key;
        $config['env']          = $api->env;

        if($config['env'] == GlobalConst::ENV_PRODUCTION) {
            $config['request_url']  = $api->credentials?->production_base_url;
        }else {
            $config['request_url']  = $api->credentials?->sandbox_base_url;
        }

        $missing = array_keys(array_filter([
            'client_id'     => $config['client_id'],
            'secret_key'    => $config['secret_key'],
            'request_url'   => $config['request_url'],
        ], fn($value) => blank($value)));

        if(count($missing) > 0) {
            $this->fail('config', ['message' => 'Missing gift card provider credentials: ' . implode(', ', $missing)]);
        }

        $this->config = $config;

        return $this;
    }

    /**
     * Run a provider request safely.
     *
     * Any exception, failed HTTP response or invalid JSON body is logged with its
     * real reason and turned into a GiftCardProviderException with a safe message.
     *
     * @param string $operation        Name used in logs (e.g. "products.search")
     * @param callable $request        Returns an Illuminate\Http\Client\Response
     * @param array $statusMessages    HTTP status => safe message (e.g. [404 => self::MSG_NOT_FOUND])
     * @param string $userMessage      Default safe message
     */
    public function send(string $operation, callable $request, array $statusMessages = [], string $userMessage = self::MSG_UNAVAILABLE): array
    {
        try {
            $response = $request();
        } catch (Throwable $e) {
            $this->fail($operation, ['message' => $e->getMessage(), 'exception' => get_class($e)], $userMessage);
        }

        if(!$response instanceof Response) {
            $this->fail($operation, ['message' => 'Unexpected response object'], $userMessage);
        }

        if($response->failed()) {
            $body = $response->json();
            $this->fail($operation, [
                'status'    => $response->status(),
                'errorCode' => is_array($body) ? ($body['errorCode'] ?? null) : null,
                'message'   => is_array($body) ? ($body['message'] ?? null) : mb_substr((string) $response->body(), 0, 500),
            ], $statusMessages[$response->status()] ?? $userMessage);
        }

        $json = $response->json();
        if(!is_array($json)) {
            $this->fail($operation, [
                'status'    => $response->status(),
                'message'   => 'Invalid JSON response',
                'body'      => mb_substr((string) $response->body(), 0, 500),
            ], $userMessage);
        }

        return $json;
    }

    /**
     * Log the real provider failure and throw a safe exception.
     */
    public function fail(string $operation, array $context = [], string $userMessage = self::MSG_UNAVAILABLE): never
    {
        Log::error("Gift card provider error [{$operation}]", array_merge([
            'provider'  => $this->api?->provider,
            'env'       => $this->api?->env,
            'status'    => null,
            'errorCode' => null,
            'message'   => null,
        ], $context));

        throw new GiftCardProviderException($userMessage);
    }

    /**
     * Authenticate API access token retrieve
     */
    public function accessToken()
    {
        if(!$this->config) $this->setConfig();

        $response = $this->send('auth.token', fn() => Http::post("https://auth.reloadly.com/oauth/token", [
            "client_id"     => $this->config['client_id'],
            "client_secret" => $this->config['secret_key'],
            "grant_type"    => "client_credentials",
            "audience"      => $this->config['request_url'],
        ]));

        if(empty($response['access_token'])) {
            $this->fail('auth.token', ['message' => 'Access token missing in response']);
        }

        $this->access_token = $response['access_token'];

        return $this;
    }

    /**
     * Authenticated provider request
     */
    protected function request()
    {
        if(!$this->access_token) $this->accessToken();

        return Http::withHeaders([
            'Authorization' => "Bearer " . $this->access_token,
            'Accept'        => "application/com.reloadly.giftcards-v1+json",
        ]);
    }

    /**
     * Get Countries
     */
    public function getCountries():array
    {
        $country_cache_key = $this->resolveCacheKey(self::COUNTRIES_CACHE_KEY);
        if(cache()->driver('file')->get($country_cache_key)) return cache()->driver('file')->get($country_cache_key);

        $response = $this->send('countries', fn() => $this->request()->get($this->config['request_url'] . "/countries"));

        cache()->driver('file')->put($country_cache_key, $response, 43200);

        return $response;
    }

    /**
     * Resolve cache key
     */
    public function resolveCacheKey(string $key):string
    {
        $api = $this->api;

        return str_replace(['{provider}','{env}'],[$api->provider, $api->env], $key);
    }

    /**
     * get products (raw provider response)
     */
    public function getProducts(array $params = []):array
    {
        return $this->send('products', fn() => $this->request()->get($this->config['request_url'] . "/products", $params));
    }

    /**
     * Server-side product search with pagination.
     *
     * @param string|null $name     Product name filter
     * @param string|null $country  ISO2 country code filter
     * @param int $page             1-based page
     * @param int $perPage          Page size (max 200)
     * @return array{items: array, total: int, page: int, last_page: int}
     */
    public function searchProducts(?string $name = null, ?string $country = null, int $page = 1, int $perPage = 18):array
    {
        $page       = max(1, $page);
        $perPage    = min(max(1, $perPage), self::MAX_PAGE_SIZE);
        $name       = filled($name) ? trim($name) : null;
        $country    = filled($country) ? strtoupper($country) : null;

        $params = array_filter([
            'size'          => $perPage,
            'page'          => $page,
            'productName'   => $name,
            'countryCode'   => $country,
        ], fn($value) => $value !== null);

        $cache_key = $this->resolveCacheKey(self::SEARCH_CACHE_KEY) . md5(json_encode($params));

        return cache()->driver('file')->remember($cache_key, self::SEARCH_CACHE_TTL, function() use ($params, $page, $perPage, $country) {
            $response = $this->send(
                'products.search',
                fn() => $this->request()->get($this->config['request_url'] . "/products", $params),
                $country ? [404 => self::MSG_NO_COUNTRY] : [],
            );

            $total = (int) ($response['totalElements'] ?? 0);

            return [
                'items'     => array_values($response['content'] ?? []),
                'total'     => $total,
                'page'      => $page,
                'last_page' => max(1, (int) ($response['totalPages'] ?? (int) ceil($total / $perPage))),
            ];
        });
    }

    /**
     * get specific product information
     */
    public function getProductInfo(int $productId):array
    {
        return $this->send(
            'products.show',
            fn() => $this->request()->get($this->config['request_url'] . "/products/" . $productId),
            [404 => self::MSG_NOT_FOUND],
        );
    }

    /**
     * Products for a country
     */
    public function getProductInfoByIso(string $iso):array
    {
        return $this->send(
            'countries.products',
            fn() => $this->request()->get($this->config['request_url'] . "/countries/" . $iso . "/products"),
            [404 => self::MSG_NO_COUNTRY],
            self::MSG_NO_COUNTRY,
        );
    }

    /**
     * Create gift card order
     */
    public function createOrder(array $data)
    {
        return $this->send(
            'orders.create',
            fn() => $this->request()->post($this->config['request_url'] . "/orders", $data),
            [],
            self::MSG_ORDER_FAILED,
        );
    }

    /**
     * Redeem Gift Card Codes
     */
    public function redeemCodes(string $trx_id):array
    {
        return $this->send(
            'orders.cards',
            fn() => $this->request()->get($this->config['request_url'] . "/orders/transactions/$trx_id/cards"),
            [404 => self::MSG_NOT_FOUND],
        );
    }

    /**
     * Receive API Webhook Response
     */
    public function webhookResponse(array $response_data)
    {
        return $response_data;
    }
}
