<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the user dashboard (and transaction lists), safe to run on a live database:
 * only adds indexes, skips any that already exist, no data changes.
 */
return new class extends Migration
{
    private array $indexes = [
        'trx_user_created_idx'             => ['user_id', 'created_at'],
        // covering index: the dashboard totals / chart / bill-pay queries are answered from the index only
        'trx_user_type_status_cover_idx'   => ['user_id', 'type', 'status', 'attribute', 'created_at', 'user_wallet_id', 'request_amount'],
        // merchant / agent dashboards (same queries with merchant_id / agent_id)
        'trx_merchant_created_idx'             => ['merchant_id', 'created_at'],
        'trx_merchant_type_status_cover_idx'   => ['merchant_id', 'type', 'status', 'attribute', 'created_at', 'merchant_wallet_id', 'request_amount'],
        'trx_agent_created_idx'                => ['agent_id', 'created_at'],
        'trx_agent_type_status_cover_idx'      => ['agent_id', 'type', 'status', 'attribute', 'created_at', 'agent_wallet_id', 'request_amount'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $name => $columns) {
            if (!Schema::hasIndex('transactions', $name)) {
                Schema::table('transactions', fn(Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        // MySQL drops the automatic user_id / merchant_id / agent_id foreign-key index once these indexes exist,
        // so put a plain user_id index back first, otherwise the foreign key blocks the drop.
        foreach (['user_id', 'merchant_id', 'agent_id'] as $column) {
            $fk_index = 'transactions_' . $column . '_foreign';
            if (!Schema::hasIndex('transactions', $fk_index)) {
                Schema::table('transactions', fn(Blueprint $table) => $table->index($column, $fk_index));
            }
        }
        foreach (array_keys($this->indexes) as $name) {
            if (Schema::hasIndex('transactions', $name)) {
                Schema::table('transactions', fn(Blueprint $table) => $table->dropIndex($name));
            }
        }
    }
};
