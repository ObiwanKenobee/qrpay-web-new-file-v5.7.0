<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin dashboard daily summary (so the dashboard never adds up every transaction on page load).
 * Safe for a live database: only creates new tables / adds indexes, skips anything that already exists.
 */
return new class extends Migration
{
    private array $indexes = [
        'transactions_updated_at_index'          => ['updated_at'],                    // sync: changed transactions
        'transactions_created_at_index'          => ['created_at'],                    // sync / rebuild by day
        'transactions_type_status_created_index' => ['type', 'status', 'created_at'],  // admin type lists / latest add money
    ];

    public function up(): void
    {
        if (!Schema::hasTable('dashboard_daily_stats')) {
            Schema::create('dashboard_daily_stats', function (Blueprint $table) {
                $table->id();
                $table->date('day');
                $table->string('type', 50);
                $table->string('attribute', 20)->default('');   // '' = no attribute
                $table->tinyInteger('status');
                $table->unsignedBigInteger('trx_count')->default(0);
                $table->decimal('amount', 28, 8)->default(0);     // request_amount (default currency)
                $table->decimal('profit', 28, 8)->default(0);     // admin charges (default currency)
                $table->timestamps();

                $table->unique(['day', 'type', 'attribute', 'status'], 'dashboard_daily_stats_unique');
                $table->index(['type', 'status'], 'dashboard_daily_stats_type_status_index');
            });
        }

        if (!Schema::hasTable('dashboard_stats_syncs')) {
            Schema::create('dashboard_stats_syncs', function (Blueprint $table) {
                $table->id();
                $table->timestamp('synced_at')->nullable();
                $table->timestamps();
            });
        }

        foreach ($this->indexes as $name => $columns) {
            if (!Schema::hasIndex('transactions', $name)) {
                Schema::table('transactions', fn(Blueprint $table) => $table->index($columns, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->indexes) as $name) {
            if (Schema::hasIndex('transactions', $name)) {
                Schema::table('transactions', fn(Blueprint $table) => $table->dropIndex($name));
            }
        }
        Schema::dropIfExists('dashboard_stats_syncs');
        Schema::dropIfExists('dashboard_daily_stats');
    }
};
