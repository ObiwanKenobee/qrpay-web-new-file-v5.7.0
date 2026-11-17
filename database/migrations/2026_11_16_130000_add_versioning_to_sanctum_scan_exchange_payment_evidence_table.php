<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sanctum_scan_exchange_payment_evidence', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('id');
            $table->unsignedBigInteger('supersedes_id')->nullable()->after('version');

            // MySQL requires an index for the exchange_id foreign key at all
            // times; add the composite key before dropping the old unique.
            $table->unique(['exchange_id', 'version'], 'scan_payment_evidence_exchange_version_unique');
            $table->dropUnique(['exchange_id']);
            $table->foreign('supersedes_id')
                ->references('id')
                ->on('sanctum_scan_exchange_payment_evidence');
        });
    }

    public function down(): void
    {
        Schema::table('sanctum_scan_exchange_payment_evidence', function (Blueprint $table) {
            $table->dropForeign(['supersedes_id']);
            // Restore the single-column unique before dropping the composite
            // one so the exchange_id foreign key keeps an index throughout.
            $table->unique(['exchange_id']);
            $table->dropUnique('scan_payment_evidence_exchange_version_unique');
            $table->dropColumn(['supersedes_id', 'version']);
        });
    }
};
