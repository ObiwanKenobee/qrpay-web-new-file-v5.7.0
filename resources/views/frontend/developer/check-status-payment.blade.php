@extends('frontend.layouts.developer_master')

@php

    $snippets = \App\Http\Helpers\DeveloperDocs::wrap([
        'php' => <<<'CODE'
<?php
// success.php  (your return_url)

$type    = $_GET['type'] ?? 'error';
$data    = $_GET['data'] ?? [];
$message = $_GET['message'] ?? [];

if ($type === 'success') {
    $token  = $data['token'];
    $trxId  = $data['trx_id'];
    $amount = $data['amount'];
    $custom = $data['custom'];   // your order id

    // 1. Find your order by $custom (or the saved token)
    // 2. Check the amount and mark the order as paid
    echo "Payment successful. Transaction: {$trxId}";
} else {
    echo 'Payment failed: ' . implode(', ', $message['error'] ?? []);
}
CODE,
        'laravel' => <<<'CODE'
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('success', function (Request $request) {
    if ($request->input('type') !== 'success') {
        return response()->json([
            'type'    => 'error',
            'message' => $request->input('message.error', []),
        ], 400);
    }

    $order = Order::where('reference', $request->input('data.custom'))->firstOrFail();
    $order->update([
        'status'         => 'paid',
        'transaction_id' => $request->input('data.trx_id'),
    ]);

    return response()->json([
        'type'    => 'success',
        'message' => 'Payment Success',
        'data'    => $request->input('data'),
    ]);
});
CODE,
        'node' => <<<'CODE'
// Express.js — qs parses message[code]=200 into nested objects
app.get('/success', async (req, res) => {
  const { type, data = {}, message = {} } = req.query;

  if (type !== 'success') {
    return res.status(400).json({ type: 'error', message: message.error });
  }

  const order = await Order.findOne({ reference: data.custom });
  order.status = 'paid';
  order.transactionId = data.trx_id;
  await order.save();

  res.json({ type: 'success', data });
});
CODE,
        'python' => <<<'CODE'
# Flask — query keys arrive as "data[trx_id]", "message[code]" ...
from flask import Flask, request, jsonify

app = Flask(__name__)

@app.get("/success")
def success():
    q = request.args
    if q.get("type") != "success":
        return jsonify(type="error", message=q.get("message[error][0]")), 400

    trx_id = q.get("data[trx_id]")
    custom = q.get("data[custom]")   # your order id
    amount = q.get("data[amount]")

    # find the order by custom, check amount, mark it paid
    return jsonify(type="success", trx_id=trx_id)
CODE,
    ], ['php' => 'PHP', 'node' => 'Node.js (Express)', 'python' => 'Python (Flask)']);

    $responses = [
        [200, __("Payment completed"), [
            'message' => ['code' => 200, 'success' => ['SUCCESS']],
            'data'    => [
                'token'  => '2zMRmT3KeYT2BWMAyGhqEfuw4tOYOfGXKeyKqehZ8mF1E35hMwE69gPpyo3e',
                'trx_id' => 'BP2c7sAvw75MTlrP',
                'payer'  => ['username' => 'testuser', 'email' => 'user@example.com'],
                'amount' => '100.00',
                'custom' => 'ORDER-123456789',
            ],
            'type'    => 'success',
        ]],
        [500, __("Payment could not be completed"), [
            'message' => ['code' => 500, 'error' => ['Failed to create payment! Please try again']],
            'data'    => [],
            'type'    => 'error',
        ]],
    ];
@endphp

@section('content')
<div class="dx-api">
    <div class="dx-api-doc">
        <div class="dx-eyebrow">{{ __("API Reference") }}</div>
        <h1 class="dx-title">{{ __("Check Payment Status") }}</h1>
        <p class="dx-lead">{{ __("After the customer pays, they are redirected to your return_url. The payment result is sent in the query string.") }}</p>

        <div class="dx-endpoint">
            <span class="dx-method dx-method--get">GET</span>
            <code class="dx-endpoint-url"><span class="dx-base">https://example.com/success</span>?type=success&amp;data[trx_id]=...</code>
        </div>

        @include('frontend.developer.partials.params', [
            'title'  => __("Query Parameters"),
            'params' => [
                ['type', 'string', true, __("Payment result: success or error."), 'success'],
                ['message.code', 'integer', true, __("HTTP style result code."), '200'],
                ['message.success', 'array', false, __("Success messages. Sent when type is success."), 'SUCCESS'],
                ['message.error', 'array', false, __("Error messages. Sent when type is error."), null],
                ['data.token', 'string', true, __("Payment token returned by Initiate Payment."), null],
                ['data.trx_id', 'string', true, __("Transaction ID of the payment."), 'BP2c7sAvw75MTlrP'],
                ['data.amount', 'decimal', true, __("Amount received by the merchant."), '100.00'],
                ['data.payer.username', 'string', true, __("Username of the payer."), null],
                ['data.payer.email', 'string', true, __("Email of the payer."), null],
                ['data.custom', 'string', false, __("The custom value you sent when creating the payment."), 'ORDER-123456789'],
            ],
        ])

        <div class="dx-callout dx-callout--danger">
            <i class="las la-shield-alt"></i>
            <p>{{ __("Never trust the redirect alone. Match data.token or data.custom with the order you saved and check the amount before you deliver the order.") }}</p>
        </div>
        <div class="dx-callout dx-callout--info">
            <i class="las la-undo"></i>
            <p>{{ __("If the customer cancels, or the payment cannot be processed, they are sent to your cancel_url.") }}</p>
        </div>
    </div>

    <div class="dx-api-code">
        @include('frontend.developer.partials.code', ['snippets' => $snippets, 'title' => __("Handle the return_url")])
        @include('frontend.developer.partials.response', ['responses' => $responses, 'title' => __("Query data (decoded)")])
    </div>
</div>
@endsection
