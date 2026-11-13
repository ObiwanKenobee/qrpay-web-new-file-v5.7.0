@extends('user.layouts.master')
@push('css')
<style>
    .gc-table-card {
        display: flex;
        align-items: center;
        gap: 12px;
        min-width: 200px;
    }
    .gc-table-card img {
        width: 56px;
        height: 38px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid #ded7e9;
        background: #fff;
        flex-shrink: 0;
    }
    .gc-table-card .gc-table-card-name {
        display: block;
        font-weight: 600;
        line-height: 1.3;
    }
    .gc-table-card .gc-table-card-qty {
        display: block;
        font-size: 12px;
        opacity: .75;
    }
    .gc-table-details-btn {
        padding: 6px 14px !important;
        font-size: 13px;
        white-space: nowrap;
    }
</style>
@endpush
@section('breadcrumb')
    @include('user.components.breadcrumb',['breadcrumbs' => [
        [
            'name'  => __("Dashboard"),
            'url'   => setRoute("user.dashboard"),
        ]
    ], 'active' => __($page_title)])
@endsection
@section('content')
<div class="body-wrapper">
    <div class="table-area mt-20">
        <div class="table-wrapper">
            <div class="dashboard-header-wrapper">
                <h4 class="title">{{ __($page_title) }}</h4>
                <div class="dashboard-btn-wrapper">
                    <div class="dashboard-btn">
                        <a href="{{ setRoute('user.gift.card.list') }}" class="btn--base"><i class="las la-plus me-1"></i> {{ __("Gift Cards") }}</a>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="custom-table">
                    <thead>
                        <tr>
                            <th>{{ __("Card") }}</th>
                            <th>{{ __("TRX") }}</th>
                            <th>{{ __("receiver Email") }}</th>
                            <th>{{ __("Card Total") }}</th>
                            <th>{{ __("Payable") }}</th>
                            <th>{{ __("Status") }}</th>
                            <th>{{ __("Date") }}</th>
                            <th>{{ __("Details") }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($giftCards ?? [] as $item)
                        <tr>
                            <td>
                                <div class="gc-table-card">
                                    <img src="{{ $item->card_image }}" alt="{{ $item->card_name }}" loading="lazy">
                                    <div>
                                        <span class="gc-table-card-name">{{ $item->card_name }}</span>
                                        <span class="gc-table-card-qty">{{ __("Qty") }}: {{ $item->qty }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>{{ $item->trx_id }}</td>
                            <td>{{ $item->recipient_email }}</td>
                            <td>{{ get_amount($item->card_total_amount, $item->card_currency) }}</td>
                            <td>{{ get_amount($item->total_payable, $item->user_wallet_currency) }}</td>
                            <td><span class="{{ $item->stringStatus->class }}">{{ __($item->stringStatus->value) }}</span></td>
                            <td>{{ $item->created_at?->format('d M Y, h:i A') }}</td>
                            <td>
                                <a href="{{ setRoute('user.gift.card.show', $item->trx_id) }}" class="btn--base gc-table-details-btn"><i class="las la-eye me-1"></i>{{ __("Details") }}</a>
                            </td>
                        </tr>
                        @empty
                            @include('admin.components.alerts.empty2',['colspan' => 8])
                        @endforelse
                    </tbody>
                </table>
            </div>
            {{ get_paginate($giftCards) }}
        </div>
    </div>
</div>
@endsection
