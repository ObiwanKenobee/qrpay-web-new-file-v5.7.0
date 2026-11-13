@extends('frontend.layouts.developer_master')

@php
    $snippets = \App\Http\Helpers\DeveloperDocs::wrap([
        'php' => <<<'CODE'
<?php
// checkout.php — full flow with plain PHP + cURL

const BASE_URL  = '__BASE_URL__';
const CLIENT_ID = 'YOUR_CLIENT_ID';
const SECRET_ID = 'YOUR_SECRET_KEY';

function api(string $path, array $body, ?string $token = null): array
{
    $headers = ['Accept: application/json', 'Content-Type: application/json'];
    if ($token) $headers[] = "Authorization: Bearer {$token}";

    $curl = curl_init(BASE_URL . $path);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($body),
        CURLOPT_HTTPHEADER     => $headers,
    ]);
    $result = json_decode(curl_exec($curl), true);
    curl_close($curl);

    if (($result['type'] ?? '') !== 'success') {
        throw new Exception(implode(', ', $result['message']['error'] ?? ['Unknown error']));
    }
    return $result['data'];
}

// 1. Access token
$auth = api('/authentication/token', [
    'client_id' => CLIENT_ID,
    'secret_id' => SECRET_ID,
]);

// 2. Create the payment
$payment = api('/payment/create', [
    'amount'     => '100.00',
    'currency'   => 'USD',
    'return_url' => 'https://example.com/success.php',
    'cancel_url' => 'https://example.com/cancel.php',
    'custom'     => 'ORDER-1001',
], $auth['access_token']);

// 3. Send the customer to checkout
header('Location: ' . $payment['payment_url']);
exit;
CODE,
        'laravel' => <<<'CODE'
// app/Services/PaymentGateway.php
namespace App\Services;

use Illuminate\Support\Facades\Http;

class PaymentGateway
{
    protected string $baseUrl = '__BASE_URL__';

    public function checkout(Order $order): string
    {
        $token = Http::acceptJson()
            ->post("{$this->baseUrl}/authentication/token", [
                'client_id' => config('services.gateway.client_id'),
                'secret_id' => config('services.gateway.secret_id'),
            ])
            ->throw()
            ->json('data.access_token');

        $payment = Http::acceptJson()
            ->withToken($token)
            ->post("{$this->baseUrl}/payment/create", [
                'amount'     => number_format($order->total, 2, '.', ''),
                'currency'   => 'USD',
                'return_url' => route('payment.success'),
                'cancel_url' => route('payment.cancel'),
                'custom'     => $order->reference,
            ])
            ->throw()
            ->json('data');

        $order->update(['gateway_token' => $payment['token']]);

        return $payment['payment_url'];
    }
}

// Controller
return redirect()->away(app(PaymentGateway::class)->checkout($order));
CODE,
        'node' => <<<'CODE'
// checkout.js — Node.js 18+ (built-in fetch)
const BASE_URL = '__BASE_URL__';

async function api(path, body, token) {
  const res = await fetch(BASE_URL + path, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      ...(token && { Authorization: `Bearer ${token}` }),
    },
    body: JSON.stringify(body),
  });
  const result = await res.json();
  if (result.type !== 'success') throw new Error(result.message.error.join(', '));
  return result.data;
}

app.post('/checkout', async (req, res) => {
  const { access_token } = await api('/authentication/token', {
    client_id: process.env.CLIENT_ID,
    secret_id: process.env.SECRET_ID,
  });

  const payment = await api('/payment/create', {
    amount: '100.00',
    currency: 'USD',
    return_url: 'https://example.com/success',
    cancel_url: 'https://example.com/cancel',
    custom: 'ORDER-1001',
  }, access_token);

  res.redirect(payment.payment_url);
});
CODE,
        'python' => <<<'CODE'
# checkout.py — Python + requests
import os
import requests

BASE_URL = "__BASE_URL__"

def api(path, body, token=None):
    headers = {"Accept": "application/json", "Content-Type": "application/json"}
    if token:
        headers["Authorization"] = f"Bearer {token}"
    result = requests.post(BASE_URL + path, json=body, headers=headers).json()
    if result.get("type") != "success":
        raise Exception(", ".join(result["message"]["error"]))
    return result["data"]

auth = api("/authentication/token", {
    "client_id": os.environ["CLIENT_ID"],
    "secret_id": os.environ["SECRET_ID"],
})

payment = api("/payment/create", {
    "amount": "100.00",
    "currency": "USD",
    "return_url": "https://example.com/success",
    "cancel_url": "https://example.com/cancel",
    "custom": "ORDER-1001",
}, auth["access_token"])

print("Redirect the customer to:", payment["payment_url"])
CODE,
    ]);
@endphp

@section('content')
<div class="dx-article">
    <div class="dx-eyebrow">{{ __("Resources") }}</div>
    <h1 class="dx-title">{{ __("Examples") }}</h1>
    <p class="dx-lead">{{ __("A complete checkout flow: get an access token, create the payment, and redirect the customer.") }}</p>

    @include('frontend.developer.partials.code', ['snippets' => $snippets, 'title' => __("Full integration")])

    <h2>{{ __("Example project") }}</h2>
    <a href="https://github.com/appdevsx/QRPay-Gateway-Example.git" target="_blank" rel="noopener" class="dx-card">
        <div class="dx-card-icon tone-purple"><i class="lab la-github"></i></div>
        <h4>{{ __("Go to GitHub Repository") }} <i class="las la-external-link-alt"></i></h4>
        <p>{{ __("A ready-made project you can clone and run.") }}</p>
    </a>
</div>
@endsection
