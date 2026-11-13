<?php

namespace Tests\Feature\SanctumScan;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeEvent;
use App\Models\SanctumScanExchangePaymentEvidence;
use App\Models\SanctumScanResource;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EconomicExchangeLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => true,
        ]);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('kyc_verified')->default(1);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('firstname');
            $table->string('lastname')->nullable();
            $table->string('username')->unique();
            $table->string('email')->unique();
            $table->string('password');
            $table->string('business_name')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('email_verified')->default(true);
            $table->boolean('sms_verified')->default(true);
            $table->boolean('kyc_verified')->default(1);
            $table->boolean('two_factor_status')->default(false);
            $table->boolean('two_factor_verified')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });

        $resourceMigration = require base_path('database/migrations/2026_11_13_150000_create_sanctum_scan_resources_table.php');
        $resourceMigration->up();
        $exchangeMigration = require base_path('database/migrations/2026_11_13_160000_create_sanctum_scan_exchanges_table.php');
        $exchangeMigration->up();
        $eventMigration = require base_path('database/migrations/2026_11_13_161000_create_sanctum_scan_exchange_events_table.php');
        $eventMigration->up();
        $paymentEvidenceMigration = require base_path('database/migrations/2026_11_13_162000_create_sanctum_scan_exchange_payment_evidence_table.php');
        $paymentEvidenceMigration->up();

        // Keep Passport authentication, participant status and 2FA checks active.
        // Disable only environment-dependent maintenance, PIN and global KYC gates.
        $this->withoutMiddleware([
            \App\Http\Middleware\Admin\SystemMaintenanceApi::class,
            \App\Http\Middleware\KycApi::class,
            \App\Http\Middleware\User\PinSetupGuard::class,
            \App\Http\Middleware\Merchant\PinSetupGuard::class,
        ]);
    }

    private function makeMerchant(string $username, string $businessName): Merchant
    {
        return Merchant::query()->create([
            'firstname' => 'Test',
            'lastname' => 'Merchant',
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => bcrypt('not-a-real-password'),
            'business_name' => $businessName,
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    private function makeBuyer(string $username): User
    {
        return User::query()->create([
            'firstname' => 'Test',
            'lastname' => 'Buyer',
            'username' => $username,
            'email' => $username . '@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'email_verified' => true,
            'sms_verified' => true,
            'kyc_verified' => 1,
            'two_factor_status' => false,
            'two_factor_verified' => true,
        ]);
    }

    private function makeResource(Merchant $merchant, string $name = 'Shangi potato batch'): SanctumScanResource
    {
        return SanctumScanResource::query()->create([
            'merchant_id' => $merchant->getKey(),
            'resource_type' => 'agricultural_batch',
            'display_name' => $name,
            'description' => 'Fresh produce batch for local trade.',
            'category' => 'Potatoes',
            'reference_code' => 'RNG-SHG-005',
            'variety' => 'Shangi',
            'origin_label' => 'Rongai, Nakuru County',
            'occurred_on' => '2026-11-10',
            'quantity' => '500.0000',
            'unit' => 'kg',
            'status' => 'published',
        ]);
    }

    private function issueOffer(Merchant $merchant, User $buyer, SanctumScanResource $resource): array
    {
        $response = $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges', [
                'resource_id' => $resource->getKey(),
                'buyer_username' => $buyer->username,
                'quantity' => '100.0000',
                'unit' => 'kg',
                'amount' => '25000.00',
                'currency' => 'KES',
                'terms' => 'Collect from the agreed local market point.',
                'expires_in_hours' => 48,
            ]);

        $response->assertCreated();

        return [
            'id' => $response->json('data.exchange.id'),
            'response' => $response,
        ];
    }

    public function test_complete_exchange_lifecycle_keeps_payment_evidence_separate_from_settlement(): void
    {
        $merchant = $this->makeMerchant('rongai-produce', 'Rongai Produce Collective');
        $buyer = $this->makeBuyer('nakuru-buyer');
        $resource = $this->makeResource($merchant);

        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->assertSame('offered', $offer['response']->json('data.exchange.status'));
        $this->assertSame('RNG-SHG-005', $offer['response']->json('data.exchange.resource.reference_code'));

        $buyerList = $this->actingAs($buyer, 'api')
            ->getJson('/api/user/sanctum/exchanges');

        $buyerList->assertOk()
            ->assertJsonCount(1, 'data.exchanges')
            ->assertJsonPath('data.exchanges.0.id', $exchangeId)
            ->assertJsonPath('data.exchanges.0.status', 'offered')
            ->assertJsonPath('data.exchanges.0.merchant.business_name', 'Rongai Produce Collective');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'accepted');

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispatch', [
                'dispatch_reference' => 'LOCAL-PICKUP-005',
                'dispatch_note' => 'Goods made available for collection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'dispatched');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/receipt', [
                'receipt_note' => 'Goods received by buyer.',
            ])
            ->assertOk()
            ->assertJsonPath('data.exchange.status', 'received');

        $paymentResponse = $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/payment-reference', [
                'rail' => 'mpesa',
                'reference' => 'QJK8XY5ABCDE',
                'amount' => '25000.00000000',
                'currency' => 'KES',
            ]);

        $paymentResponse->assertCreated()
            ->assertJsonPath('data.payment_evidence.verification_status', 'unverified')
            ->assertJsonPath('data.payment_evidence.message', 'Reference recorded. This is not confirmation of payment or settlement.');

        $exchange = SanctumScanExchange::query()->findOrFail($exchangeId);
        $this->assertSame('received', $exchange->status);
        $this->assertSame('25000.00000000', $exchange->amount);
        $this->assertSame(1, SanctumScanExchangePaymentEvidence::query()->where('exchange_id', $exchangeId)->count());
        $this->assertSame('unverified', $exchange->paymentEvidence->verification_status);

        $eventTypes = SanctumScanExchangeEvent::query()
            ->where('exchange_id', $exchangeId)
            ->orderBy('id')
            ->pluck('event_type')
            ->all();

        $this->assertSame([
            'offer_issued',
            'offer_accepted',
            'dispatch_declared',
            'receipt_confirmed',
            'payment_reference_submitted',
        ], $eventTypes);

        $paymentEvent = SanctumScanExchangeEvent::query()
            ->where('exchange_id', $exchangeId)
            ->where('event_type', 'payment_reference_submitted')
            ->firstOrFail();

        $this->assertSame('unverified', $paymentEvent->verification_status);
        $this->assertSame('api', $paymentEvent->recorded_by_guard);
        $this->assertSame($buyer->getKey(), $paymentEvent->recorded_by_id);
    }

    public function test_merchant_cannot_issue_an_offer_for_another_merchants_resource(): void
    {
        $owner = $this->makeMerchant('resource-owner', 'Resource Owner');
        $otherMerchant = $this->makeMerchant('other-owner', 'Other Owner');
        $buyer = $this->makeBuyer('buyer-one');
        $resource = $this->makeResource($otherMerchant);

        $this->actingAs($owner, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges', [
                'resource_id' => $resource->getKey(),
                'buyer_username' => $buyer->username,
                'quantity' => 1,
                'unit' => 'kg',
                'amount' => '100',
                'currency' => 'KES',
            ])
            ->assertNotFound();

        $this->assertSame(0, SanctumScanExchange::query()->count());
    }

    public function test_only_the_named_buyer_can_accept_or_view_an_offer(): void
    {
        $merchant = $this->makeMerchant('named-buyer-seller', 'Named Buyer Seller');
        $buyer = $this->makeBuyer('named-buyer');
        $otherBuyer = $this->makeBuyer('other-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($otherBuyer, 'api')
            ->getJson('/api/user/sanctum/exchanges')
            ->assertOk()
            ->assertJsonCount(0, 'data.exchanges');

        $this->actingAs($otherBuyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertNotFound();

        $this->assertSame('offered', SanctumScanExchange::findOrFail($offer['id'])->status);
    }

    public function test_buyer_cannot_confirm_receipt_before_dispatch(): void
    {
        $merchant = $this->makeMerchant('receipt-seller', 'Receipt Seller');
        $buyer = $this->makeBuyer('receipt-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/receipt')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_exchange_state');
    }

    public function test_payment_reference_must_match_agreed_amount_and_currency(): void
    {
        $merchant = $this->makeMerchant('amount-seller', 'Amount Seller');
        $buyer = $this->makeBuyer('amount-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/payment-reference', [
                'rail' => 'mpesa',
                'reference' => 'WRONG-AMOUNT',
                'amount' => '24999',
                'currency' => 'KES',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payment_amount_mismatch');

        $this->assertSame(0, SanctumScanExchangePaymentEvidence::query()->count());
    }

    public function test_payment_reference_can_only_be_submitted_once(): void
    {
        $merchant = $this->makeMerchant('single-ref-seller', 'Single Reference Seller');
        $buyer = $this->makeBuyer('single-ref-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $payload = [
            'rail' => 'bank_transfer',
            'reference' => 'BANK-REF-005',
            'amount' => '25000',
            'currency' => 'KES',
        ];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/payment-reference', $payload)
            ->assertCreated();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/payment-reference', [
                ...$payload,
                'reference' => 'BANK-REF-SECOND',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'payment_reference_exists');

        $this->assertSame(1, SanctumScanExchangePaymentEvidence::query()->count());
    }

    public function test_expired_offer_cannot_be_accepted_and_is_recorded_as_expired(): void
    {
        $merchant = $this->makeMerchant('expiry-seller', 'Expiry Seller');
        $buyer = $this->makeBuyer('expiry-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        SanctumScanExchange::query()
            ->whereKey($offer['id'])
            ->update(['expires_at' => now()->subMinute()]);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'offer_expired');

        $this->assertSame('expired', SanctumScanExchange::findOrFail($offer['id'])->status);
        $this->assertDatabaseHas('sanctum_scan_exchange_events', [
            'exchange_id' => $offer['id'],
            'event_type' => 'offer_expired',
            'verification_status' => 'system_recorded',
        ]);
    }

    public function test_archiving_resource_after_acceptance_does_not_rewrite_the_agreement_state(): void
    {
        $merchant = $this->makeMerchant('accepted-archival-seller', 'Accepted Archival Seller');
        $buyer = $this->makeBuyer('accepted-archival-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $resource->forceFill([
            'status' => 'archived',
            'archived_at' => now(),
        ])->save();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_exchange_state');

        $this->assertSame('accepted', SanctumScanExchange::findOrFail($offer['id'])->status);
    }

    public function test_buyer_cannot_accept_an_offer_after_the_resource_has_been_archived(): void
    {
        $merchant = $this->makeMerchant('archived-resource-seller', 'Archived Resource Seller');
        $buyer = $this->makeBuyer('archived-resource-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $resource->forceFill([
            'status' => 'archived',
            'archived_at' => now(),
        ])->save();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'offer_withdrawn');

        $this->assertSame('cancelled', SanctumScanExchange::findOrFail($offer['id'])->status);
        $this->assertDatabaseHas('sanctum_scan_exchange_events', [
            'exchange_id' => $offer['id'],
            'event_type' => 'offer_withdrawn',
            'verification_status' => 'system_recorded',
        ]);
    }

    public function test_merchant_cannot_cancel_an_offer_after_buyer_acceptance(): void
    {
        $merchant = $this->makeMerchant('accepted-offer-seller', 'Accepted Offer Seller');
        $buyer = $this->makeBuyer('accepted-offer-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $offer['id'] . '/cancel')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_exchange_state');

        $this->assertSame('accepted', SanctumScanExchange::findOrFail($offer['id'])->status);
    }

    public function test_submitted_payment_evidence_is_immutable(): void
    {
        $merchant = $this->makeMerchant('immutable-evidence-seller', 'Immutable Evidence Seller');
        $buyer = $this->makeBuyer('immutable-evidence-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/payment-reference', [
                'rail' => 'mpesa',
                'reference' => 'IMMUTABLE-REF',
                'amount' => '25000',
                'currency' => 'KES',
            ])
            ->assertCreated();

        $evidence = SanctumScanExchangePaymentEvidence::query()->firstOrFail();

        try {
            $evidence->update(['verification_status' => 'verified']);
            $this->fail('Submitted payment evidence should not be mutable.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Submitted payment evidence is immutable and requires a separate verification record.',
                $exception->getMessage()
            );
        }

        $this->assertSame('unverified', $evidence->fresh()->verification_status);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sanctum_scan_exchange_payment_evidence');
        Schema::dropIfExists('sanctum_scan_exchange_events');
        Schema::dropIfExists('sanctum_scan_exchanges');
        Schema::dropIfExists('sanctum_scan_resources');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('users');
        DB::purge('sqlite');

        parent::tearDown();
    }
}
