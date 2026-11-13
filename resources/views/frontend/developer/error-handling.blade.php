@extends('frontend.layouts.developer_master')

@php
    $snippets = \App\Http\Helpers\DeveloperDocs::wrap([
        'php' => <<<'CODE'
<?php
$response = curl_exec($curl);
$status   = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$result   = json_decode($response, true);

if ($status !== 200 || ($result['type'] ?? '') !== 'success') {
    $errors = $result['message']['error'] ?? ['Unknown error'];

    if ($status === 401) {
        // token expired: request a new access token and retry once
    }

    throw new Exception(implode(', ', $errors));
}

$data = $result['data'];
CODE,
        'laravel' => <<<'CODE'
$response = Http::acceptJson()
    ->withToken($accessToken)
    ->post($baseUrl . '/payment/create', $payload);

if ($response->status() === 401) {
    // token expired: request a new access token and retry once
}

if ($response->failed() || $response->json('type') !== 'success') {
    $errors = $response->json('message.error', ['Unknown error']);
    Log::warning('Payment API error', ['errors' => $errors]);

    return back()->withErrors($errors);
}

return redirect()->away($response->json('data.payment_url'));
CODE,
        'node' => <<<'CODE'
const response = await fetch(`${baseUrl}/payment/create`, options);
const result = await response.json();

if (!response.ok || result.type !== 'success') {
  const errors = result?.message?.error ?? ['Unknown error'];

  if (response.status === 401) {
    // token expired: request a new access token and retry once
  }

  throw new Error(errors.join(', '));
}

const { payment_url } = result.data;
CODE,
        'python' => <<<'CODE'
response = requests.post(f"{base_url}/payment/create", json=payload, headers=headers)
result = response.json()

if response.status_code != 200 or result.get("type") != "success":
    errors = result.get("message", {}).get("error", ["Unknown error"])

    if response.status_code == 401:
        pass  # token expired: request a new access token and retry once

    raise Exception(", ".join(errors))

payment_url = result["data"]["payment_url"]
CODE,
    ]);
@endphp

@section('content')
<div class="dx-api">
    <div class="dx-api-doc">
        <div class="dx-eyebrow">{{ __("Resources") }}</div>
        <h1 class="dx-title">{{ __("Error Handling") }}</h1>
        <p class="dx-lead">{{ __("In case of an error, the API will return an error response containing a specific error code") }} <strong>{{ __("400, 403 Failed") }}</strong> {{ __("and a user-friendly message. Refer to our API documentation for a comprehensive list of error codes and their descriptions.") }}</p>

        <h3>{{ __("Error object") }}</h3>
        @include('frontend.developer.partials.params', [
            'params' => [
                ['type', 'string', true, __("Always error for failed requests."), 'error'],
                ['message.code', 'integer', true, __("Same as the HTTP status code."), '400'],
                ['message.error', 'array', true, __("One or more readable error messages."), null],
                ['data', 'array', true, __("Empty for errors."), '[]'],
            ],
        ])

        <h3>{{ __("Recommended handling") }}</h3>
        <ul class="dx-list">
            <li><strong>400</strong> — {{ __("Show the message to your team and fix the request. Do not retry unchanged.") }}</li>
            <li><strong>401</strong> — {{ __("Request a new access token and retry once.") }}</li>
            <li><strong>403 / 404</strong> — {{ __("Check your API keys and the selected environment.") }}</li>
            <li><strong>500</strong> — {{ __("Wait a moment and retry. Contact support if it keeps failing.") }}</li>
        </ul>

        <p>{{ __("See all codes on the") }} <a href="{{ setRoute('developer.response.code') }}">{{ __("Response Codes") }}</a> {{ __("page.") }}</p>
    </div>
    <div class="dx-api-code">
        @include('frontend.developer.partials.code', ['snippets' => $snippets, 'title' => __("Handle errors")])
        @include('frontend.developer.partials.response', [
            'responses' => [
                [400, __("Validation failed"), ['message' => ['code' => 400, 'error' => ['The amount field is required.', 'The currency field is required.']], 'data' => [], 'type' => 'error']],
                [401, __("Access token expired"), ['message' => ['code' => 401, 'error' => ['Request token is expired']], 'data' => [], 'type' => 'error']],
                [403, __("Missing or invalid access token"), ['message' => ['code' => 403, 'error' => ['Access denied! Token not found']], 'data' => [], 'type' => 'error']],
            ],
        ])
    </div>
</div>
@endsection
