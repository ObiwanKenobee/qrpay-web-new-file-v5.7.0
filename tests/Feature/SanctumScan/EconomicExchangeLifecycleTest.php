<?php

namespace Tests\Feature\SanctumScan;

use App\Models\Merchants\Merchant;
use App\Models\SanctumScanExchange;
use App\Models\SanctumScanExchangeEvent;
use App\Models\SanctumScanExchangePaymentEvidence;
use App\Models\SanctumScanExchangeDispute;
use App\Models\SanctumScanExchangeDisputeMessage;
use App\Models\SanctumScanResource;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\SanctumScanExchangeDisputeEvidence;
use App\Models\SanctumScanExchangeEvidenceReview;
use App\Services\SanctumScan\DisputeEvidenceManager;
use App\Services\SanctumScan\IndependentVerificationManager;
use App\Models\SanctumScanIndependentAttestation;
use App\Models\SanctumScanVerifierRegistryEvent;
use App\Models\SanctumScanVerifierCredentialReview;
use App\Models\SanctumScanVerifierConflictDeclaration;
use App\Models\SanctumScanAttestationAppeal;
use App\Models\SanctumScanAttestationAppealDecision;
use App\Models\SanctumScanAttestationRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

        Schema::create('admins', function (Blueprint $table) {
            $table->id();
            $table->string('firstname')->nullable();
            $table->string('lastname')->nullable();
            $table->string('username')->nullable()->unique();
            $table->string('email')->nullable()->unique();
            $table->string('password')->nullable();
            $table->boolean('status')->default(true);
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
        $disputeMigration = require base_path('database/migrations/2026_11_13_163000_create_sanctum_scan_exchange_disputes_table.php');
        $disputeMigration->up();
        $disputeMessageMigration = require base_path('database/migrations/2026_11_13_164000_create_sanctum_scan_exchange_dispute_messages_table.php');
        $disputeMessageMigration->up();
        $evidenceMigration = require base_path('database/migrations/2026_11_13_165000_create_sanctum_scan_exchange_dispute_evidence_table.php');
        $evidenceMigration->up();
        $evidenceReviewMigration = require base_path('database/migrations/2026_11_13_166000_create_sanctum_scan_exchange_evidence_reviews_table.php');
        $evidenceReviewMigration->up();

        $verifierInstitutionMigration = require base_path('database/migrations/2026_11_13_167000_create_sanctum_scan_verifier_institutions_table.php');
        $verifierInstitutionMigration->up();
        $verifierMembershipMigration = require base_path('database/migrations/2026_11_13_168000_create_sanctum_scan_verifier_memberships_table.php');
        $verifierMembershipMigration->up();
        $verificationMandateMigration = require base_path('database/migrations/2026_11_13_169000_create_sanctum_scan_evidence_verification_mandates_table.php');
        $verificationMandateMigration->up();
        $attestationMigration = require base_path('database/migrations/2026_11_13_170000_create_sanctum_scan_independent_attestations_table.php');
        $attestationMigration->up();
        $verifierEventMigration = require base_path('database/migrations/2026_11_13_171000_create_sanctum_scan_verifier_registry_events_table.php');
        $verifierEventMigration->up();
        $credentialReviewMigration = require base_path('database/migrations/2026_11_13_172000_create_sanctum_scan_verifier_credential_reviews_table.php');
        $credentialReviewMigration->up();
        $conflictDeclarationMigration = require base_path('database/migrations/2026_11_13_173000_create_sanctum_scan_verifier_conflict_declarations_table.php');
        $conflictDeclarationMigration->up();
        $attestationAppealMigration = require base_path('database/migrations/2026_11_13_174000_create_sanctum_scan_attestation_appeals_table.php');
        $attestationAppealMigration->up();
        $appealDecisionMigration = require base_path('database/migrations/2026_11_13_175000_create_sanctum_scan_attestation_appeal_decisions_table.php');
        $appealDecisionMigration->up();
        $attestationRevisionMigration = require base_path('database/migrations/2026_11_13_176000_create_sanctum_scan_attestation_revisions_table.php');
        $attestationRevisionMigration->up();

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

    public function test_merchant_cannot_issue_an_offer_to_an_unverified_buyer_account(): void
    {
        $merchant = $this->makeMerchant('unverified-buyer-seller', 'Unverified Buyer Seller');
        $buyer = $this->makeBuyer('unverified-buyer');
        $buyer->forceFill(['sms_verified' => false])->save();
        $resource = $this->makeResource($merchant);

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges', [
                'resource_id' => $resource->getKey(),
                'buyer_username' => $buyer->username,
                'quantity' => '1',
                'unit' => 'kg',
                'amount' => '100',
                'currency' => 'KES',
            ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'buyer_unavailable');

        $this->assertSame(0, SanctumScanExchange::query()->count());
    }

    public function test_merchant_cannot_dispatch_before_buyer_acceptance(): void
    {
        $merchant = $this->makeMerchant('preaccept-dispatch-seller', 'Preaccept Dispatch Seller');
        $buyer = $this->makeBuyer('preaccept-dispatch-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $offer['id'] . '/dispatch')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'invalid_exchange_state');

        $this->assertSame('offered', SanctumScanExchange::findOrFail($offer['id'])->status);
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


    public function test_bilateral_dispute_can_be_resolved_only_by_the_other_participant(): void
    {
        $merchant = $this->makeMerchant('dispute-flow-seller', 'Dispute Flow Seller');
        $buyer = $this->makeBuyer('dispute-flow-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'quantity_mismatch',
                'statement' => 'The parties need to reconcile the measured quantity.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.dispute.status', 'open')
            ->assertJsonPath('data.dispute.opened_by', 'merchant');

        $this->actingAs($buyer, 'api')
            ->getJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute')
            ->assertOk()
            ->assertJsonPath('data.dispute.messages.0.body', 'The parties need to reconcile the measured quantity.');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/messages', [
                'message' => 'I agree that we should compare the measurement records.',
            ])
            ->assertOk()
            ->assertJsonPath('data.dispute.messages.1.author', 'buyer');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/propose-resolution', [
                'summary' => 'Both parties will arrange a joint quantity check.',
            ])
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'resolution_proposed');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/accept-resolution')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'proposer_cannot_accept');

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute/accept-resolution')
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'resolved')
            ->assertJsonPath('data.dispute.resolved_by', 'merchant');

        $this->assertSame('accepted', SanctumScanExchange::findOrFail($exchangeId)->status);
        $this->assertSame(1, SanctumScanExchangeDispute::query()->where('exchange_id', $exchangeId)->count());
        $this->assertSame(4, SanctumScanExchangeDisputeMessage::query()->count());
        $this->assertDatabaseHas('sanctum_scan_exchange_events', [
            'exchange_id' => $exchangeId,
            'event_type' => 'dispute_resolution_accepted',
            'verification_status' => 'merchant_declared',
        ]);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'dispute_exists');
    }

    public function test_dispute_routes_are_scoped_to_the_exchange_participants(): void
    {
        $merchant = $this->makeMerchant('dispute-scope-seller', 'Dispute Scope Seller');
        $buyer = $this->makeBuyer('dispute-scope-buyer');
        $otherBuyer = $this->makeBuyer('dispute-scope-outsider');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/accept')
            ->assertOk();

        $this->actingAs($otherBuyer, 'api')
            ->getJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/dispute')
            ->assertNotFound();

        $this->actingAs($otherBuyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/dispute', [
                'category' => 'other',
                'statement' => 'An unauthorized statement.',
            ])
            ->assertNotFound();

        $this->assertSame(0, SanctumScanExchangeDispute::query()->count());
    }

    public function test_disputes_cannot_be_opened_before_an_offer_is_accepted(): void
    {
        $merchant = $this->makeMerchant('dispute-state-seller', 'Dispute State Seller');
        $buyer = $this->makeBuyer('dispute-state-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $offer['id'] . '/dispute', [
                'category' => 'non_delivery',
                'statement' => 'The exchange has not progressed.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'exchange_not_disputable');

        $this->assertSame(0, SanctumScanExchangeDispute::query()->count());
    }

    public function test_rejecting_a_resolution_returns_the_dispute_to_open(): void
    {
        $merchant = $this->makeMerchant('dispute-reject-seller', 'Dispute Reject Seller');
        $buyer = $this->makeBuyer('dispute-reject-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'terms_disagreement',
                'statement' => 'The parties disagree about the agreed collection terms.',
            ])
            ->assertCreated();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute/propose-resolution', [
                'summary' => 'Proposed resolution: reschedule collection.',
            ])
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'resolution_proposed');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/reject-resolution', [
                'reason' => 'The proposed time is not workable.',
            ])
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'open')
            ->assertJsonPath('data.dispute.messages.2.type', 'resolution_rejected');
    }

    public function test_initiator_cannot_withdraw_after_counterparty_response(): void
    {
        $merchant = $this->makeMerchant('dispute-withdraw-seller', 'Dispute Withdraw Seller');
        $buyer = $this->makeBuyer('dispute-withdraw-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'payment_question',
                'statement' => 'The payment reference needs clarification.',
            ])
            ->assertCreated();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/messages', [
                'message' => 'I will provide the transaction receipt for review.',
            ])
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute/withdraw')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'withdrawal_not_allowed');

        $this->assertSame('open', SanctumScanExchangeDispute::query()->firstOrFail()->status);
    }

    public function test_initiator_can_withdraw_before_counterparty_response(): void
    {
        $merchant = $this->makeMerchant('dispute-withdraw-clean-seller', 'Dispute Withdraw Clean Seller');
        $buyer = $this->makeBuyer('dispute-withdraw-clean-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'other',
                'statement' => 'Opening statement that the merchant later withdraws.',
            ])
            ->assertCreated();

        $this->actingAs($merchant, 'merchant_api')
            ->postJson('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute/withdraw')
            ->assertOk()
            ->assertJsonPath('data.dispute.status', 'withdrawn');

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'dispute_exists');
    }

    public function test_dispute_messages_are_immutable(): void
    {
        $merchant = $this->makeMerchant('dispute-immutable-seller', 'Dispute Immutable Seller');
        $buyer = $this->makeBuyer('dispute-immutable-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'quality_concern',
                'statement' => 'The quality needs further discussion.',
            ])
            ->assertCreated();

        $message = SanctumScanExchangeDisputeMessage::query()->firstOrFail();

        try {
            $message->update(['body' => 'Edited statement']);
            $this->fail('Dispute messages should be immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame(
                'Sanctum Scan dispute messages are immutable.',
                $exception->getMessage()
            );
        }

        $this->assertSame(
            'The quality needs further discussion.',
            $message->fresh()->body
        );
    }


    public function test_dispute_evidence_is_private_and_available_only_to_exchange_participants(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $merchant = $this->makeMerchant('evidence-private-seller', 'Evidence Private Seller');
        $buyer = $this->makeBuyer('evidence-private-buyer');
        $outsider = $this->makeBuyer('evidence-private-outsider');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'payment_question',
                'statement' => 'The payment reference needs to be checked against the receipt.',
            ])
            ->assertCreated();

        $upload = $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'payment_receipt',
                'caption' => 'Receipt supplied for review.',
                'file' => UploadedFile::fake()->create('payment-receipt.pdf', 32, 'application/pdf'),
            ], ['Accept' => 'application/json']);

        $upload->assertCreated()
            ->assertJsonPath('data.evidence.original_name', 'payment-receipt.pdf')
            ->assertJsonPath('data.evidence.verification.status', 'pending');

        $evidence = SanctumScanExchangeDisputeEvidence::query()->firstOrFail();
        $this->assertSame('local', $evidence->storage_disk);
        $this->assertStringStartsWith('sanctum-scan/disputes/', $evidence->storage_path);
        Storage::disk('local')->assertExists($evidence->storage_path);
        Storage::disk('public')->assertMissing($evidence->storage_path);

        $this->actingAs($merchant, 'merchant_api')
            ->get('/merchant-api/merchant/sanctum/exchanges/' . $exchangeId . '/dispute/evidence')
            ->assertOk()
            ->assertJsonPath('data.evidence.0.id', $evidence->getKey());

        $this->actingAs($outsider, 'api')
            ->get('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence/' . $evidence->getKey() . '/download')
            ->assertNotFound();

        $this->actingAs($buyer, 'api')
            ->get('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence/' . $evidence->getKey() . '/download')
            ->assertDownload('payment-receipt.pdf');

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence/' . $evidence->getKey() . '/download')
            ->assertUnauthorized();
    }

    public function test_duplicate_dispute_evidence_is_rejected(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('evidence-duplicate-seller', 'Evidence Duplicate Seller');
        $buyer = $this->makeBuyer('evidence-duplicate-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'quality_concern',
                'statement' => 'The product quality evidence needs review.',
            ])
            ->assertCreated();

        $firstFile = UploadedFile::fake()->createWithContent('same-receipt.pdf', '%PDF-1.4 test evidence');
        $first = $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'quality_record',
                'file' => $firstFile,
            ], ['Accept' => 'application/json']);
        $first->assertCreated();

        $secondFile = UploadedFile::fake()->createWithContent('same-receipt-copy.pdf', '%PDF-1.4 test evidence');
        $second = $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'quality_record',
                'file' => $secondFile,
            ], ['Accept' => 'application/json']);

        $second->assertStatus(409)
            ->assertJsonPath('error.code', 'duplicate_evidence');

        $this->assertSame(1, SanctumScanExchangeDisputeEvidence::query()->count());
    }

    public function test_admin_evidence_review_is_hash_bound_and_immutable(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('evidence-review-seller', 'Evidence Review Seller');
        $buyer = $this->makeBuyer('evidence-review-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'payment_question',
                'statement' => 'The supplied payment receipt should be checked.',
            ])
            ->assertCreated();

        $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'payment_receipt',
                'caption' => 'Receipt supplied for review.',
                'file' => UploadedFile::fake()->createWithContent('receipt.pdf', '%PDF-1.4 review fixture'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $evidence = SanctumScanExchangeDisputeEvidence::query()->firstOrFail();
        $adminId = DB::table('admins')->insertGetId([
            'firstname' => 'Independent',
            'lastname' => 'Reviewer',
            'username' => 'evidence-reviewer',
            'email' => 'evidence-reviewer@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = app(DisputeEvidenceManager::class);
        $review = $manager->review(
            (int) $evidence->getKey(),
            $adminId,
            'verified',
            'document_review',
            'The document structure and reference information were reviewed against the submitted dispute context.'
        );

        $this->assertInstanceOf(SanctumScanExchangeEvidenceReview::class, $review);
        $this->assertSame('verified', $review->review_status);
        $this->assertSame($evidence->sha256, $review->evidence_sha256);
        $this->assertSame(1, SanctumScanExchangeEvidenceReview::query()->count());
        $this->assertDatabaseHas('sanctum_scan_exchange_events', [
            'exchange_id' => $exchangeId,
            'event_type' => 'dispute_evidence_reviewed',
            'recorded_by_guard' => 'admin',
            'recorded_by_id' => $adminId,
            'verification_status' => 'platform_verified',
        ]);

        try {
            $review->update(['review_status' => 'rejected']);
            $this->fail('Evidence reviews should be immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame('Sanctum Scan evidence reviews are immutable.', $exception->getMessage());
        }

        try {
            $evidence->update(['caption' => 'Modified after submission']);
            $this->fail('Submitted evidence metadata should be immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame('Submitted dispute evidence is immutable.', $exception->getMessage());
        }

        $this->assertSame('verified', $review->fresh()->review_status);
        $this->assertSame('Receipt supplied for review.', $evidence->fresh()->caption ?? null);
    }

    public function test_evidence_tampering_prevents_a_review_decision_and_download(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('evidence-tamper-seller', 'Evidence Tamper Seller');
        $buyer = $this->makeBuyer('evidence-tamper-buyer');
        $resource = $this->makeResource($merchant);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'other',
                'statement' => 'Evidence integrity should be checked.',
            ])
            ->assertCreated();

        $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'other',
                'file' => UploadedFile::fake()->createWithContent('evidence.pdf', '%PDF-1.4 original'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $evidence = SanctumScanExchangeDisputeEvidence::query()->firstOrFail();
        Storage::disk('local')->put($evidence->storage_path, 'tampered content');

        $adminId = DB::table('admins')->insertGetId([
            'firstname' => 'Integrity',
            'lastname' => 'Reviewer',
            'username' => 'integrity-reviewer',
            'email' => 'integrity-reviewer@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $manager = app(DisputeEvidenceManager::class);
        $this->assertSame(
            'evidence_integrity_failure',
            $manager->review(
                (int) $evidence->getKey(),
                $adminId,
                'verified',
                'document_review',
                'The document was inspected and appeared valid based on the review criteria.'
            )
        );

        $this->assertSame(0, SanctumScanExchangeEvidenceReview::query()->count());

        $this->actingAs($buyer, 'api')
            ->get('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence/' . $evidence->getKey() . '/download')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'evidence_integrity_failure');
    }

    private function makeAdminForVerification(string $suffix): int
    {
        return (int) DB::table('admins')->insertGetId([
            'firstname' => 'Registry',
            'lastname' => 'Administrator',
            'username' => 'registry-admin-' . $suffix,
            'email' => 'registry-admin-' . $suffix . '@example.test',
            'password' => bcrypt('not-a-real-password'),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function makeDisputeEvidenceForVerification(
        Merchant $merchant,
        User $buyer,
        string $suffix
    ): array {
        $resource = $this->makeResource($merchant, 'Verification batch ' . $suffix);
        $offer = $this->issueOffer($merchant, $buyer, $resource);
        $exchangeId = $offer['id'];

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/accept')
            ->assertOk();

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute', [
                'category' => 'quality_concern',
                'statement' => 'This evidence needs an independent inspection for the test fixture.',
            ])
            ->assertCreated();

        $this->actingAs($buyer, 'api')
            ->post('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence', [
                'evidence_type' => 'quality_record',
                'file' => UploadedFile::fake()->createWithContent(
                    'quality-proof-' . $suffix . '.pdf',
                    '%PDF-1.4 independent verification fixture'
                ),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        return [
            'exchange_id' => $exchangeId,
            'evidence' => SanctumScanExchangeDisputeEvidence::query()->latest('id')->firstOrFail(),
        ];
    }

    public function test_verifier_account_must_remain_active_and_verified_when_accepting_an_invitation(): void
    {
        $verifier = $this->makeBuyer('unverified-verifier-account');
        $adminId = $this->makeAdminForVerification('acceptance-eligibility');
        $manager = app(IndependentVerificationManager::class);

        $institution = $manager->registerInstitution($adminId, [
            'name' => 'Acceptance Eligibility Institute',
            'organization_type' => 'research_institute',
            'country_code' => 'KE',
        ]);
        $membership = $manager->inviteMember(
            (int) $institution->getKey(),
            (int) $verifier->getKey(),
            $adminId
        );

        $verifier->forceFill(['email_verified' => false])->save();

        $this->assertSame(
            'verifier_account_unavailable',
            $manager->respondToInvitation(
                (int) $membership->getKey(),
                (int) $verifier->getKey(),
                true
            )
        );
        $this->assertSame('invited', $membership->fresh()->status);
        $this->assertNull($membership->fresh()->accepted_at);
    }

    public function test_independent_verifier_requires_user_acceptance_and_an_evidence_specific_mandate(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('independent-verify-seller', 'Independent Verify Seller');
        $buyer = $this->makeBuyer('independent-verify-buyer');
        $verifier = $this->makeBuyer('independent-quality-verifier');
        $outsider = $this->makeBuyer('independent-outsider');
        $fixture = $this->makeDisputeEvidenceForVerification($merchant, $buyer, 'm8');
        $evidence = $fixture['evidence'];
        $adminId = $this->makeAdminForVerification('m8');
        $manager = app(IndependentVerificationManager::class);

        $institution = $manager->registerInstitution($adminId, [
            'name' => 'Nakuru Independent Quality Institute',
            'organization_type' => 'inspection_body',
            'country_code' => 'ke',
            'registration_reference' => 'KE-INSPECT-008',
            'website' => 'https://example.test/quality-institute',
        ]);
        $membership = $manager->inviteMember(
            (int) $institution->getKey(),
            (int) $verifier->getKey(),
            $adminId
        );
        $this->assertInstanceOf(\App\Models\SanctumScanVerifierMembership::class, $membership);
        $this->assertSame('invited', $membership->status);

        $this->assertSame(
            'invitation_requires_user_acceptance',
            $manager->setMembershipStatus((int) $membership->getKey(), $adminId, 'active')
        );

        $this->actingAs($outsider, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey() . '/accept')
            ->assertNotFound();

        $this->actingAs($verifier, 'api')
            ->getJson('/api/user/sanctum/verifier/memberships')
            ->assertOk()
            ->assertJsonPath('data.memberships.0.status', 'invited');

        $this->actingAs($verifier, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey() . '/accept')
            ->assertOk()
            ->assertJsonPath('data.membership.status', 'active');

        $downloadUrl = '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
            . '/evidence/' . $evidence->getKey() . '/download';

        $this->actingAs($verifier, 'api')->get($downloadUrl)->assertForbidden();

        $mandate = $manager->grantMandate(
            (int) $evidence->getKey(),
            (int) $institution->getKey(),
            $adminId,
            'quality_observation',
            'Inspect the submitted quality record against the stated scope.',
            now()->addDays(7)->toDateTimeString()
        );
        $this->assertInstanceOf(
            \App\Models\SanctumScanEvidenceVerificationMandate::class,
            $mandate
        );

        // A mandate alone does not authorize evidence access: both the institution
        // and the verifier need current, administrator-reviewed credentials.
        $this->actingAs($verifier, 'api')
            ->get($downloadUrl)
            ->assertForbidden();

        $institutionCredential = $manager->recordCredentialReview(
            (int) $institution->getKey(),
            null,
            $adminId,
            'institution_registration',
            'business_registration',
            'Kenya Business Registration Service',
            'BRS-REG-008',
            'KE',
            null,
            'registry_lookup',
            'verified',
            'Institution registration was checked against the stated registry reference.',
            now()->addYear()->toDateTimeString()
        );
        $this->assertInstanceOf(SanctumScanVerifierCredentialReview::class, $institutionCredential);

        $memberCredential = $manager->recordCredentialReview(
            (int) $institution->getKey(),
            (int) $membership->getKey(),
            $adminId,
            'quality_observation',
            'professional_inspector',
            'Kenya Bureau of Standards',
            'KEBS-INSPECTOR-008',
            'KE',
            null,
            'issuer_confirmation',
            'verified',
            'Verifier qualification and scope were checked with the stated issuing body.',
            now()->addYear()->toDateTimeString()
        );
        $this->assertInstanceOf(SanctumScanVerifierCredentialReview::class, $memberCredential);

        // A newer negative review supersedes the earlier positive review.
        $manager->recordCredentialReview(
            (int) $institution->getKey(),
            (int) $membership->getKey(),
            $adminId,
            'quality_observation',
            'professional_inspector',
            'Kenya Bureau of Standards',
            'KEBS-INSPECTOR-008',
            'KE',
            null,
            'issuer_confirmation',
            'unable_to_verify',
            'The issuing body could not confirm the credential during a subsequent check.',
            null
        );
        $this->actingAs($verifier, 'api')
            ->get($downloadUrl)
            ->assertForbidden();

        $manager->recordCredentialReview(
            (int) $institution->getKey(),
            (int) $membership->getKey(),
            $adminId,
            'quality_observation',
            'professional_inspector',
            'Kenya Bureau of Standards',
            'KEBS-INSPECTOR-008-RECHECK',
            'KE',
            null,
            'issuer_confirmation',
            'verified',
            'A fresh confirmation restored this exact scope after the prior review was unable to verify it.',
            now()->addYear()->toDateTimeString()
        );

        // Credentials are insufficient until the verifier makes an explicit,
        // immutable conflict declaration for this exact mandate.
        $this->actingAs($verifier, 'api')
            ->get($downloadUrl)
            ->assertForbidden();

        $this->actingAs($verifier, 'api')
            ->postJson(
                '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                    . '/evidence/' . $evidence->getKey() . '/conflict-declaration',
                [
                    'has_conflict' => false,
                    'disclosure_statement' => 'I have disclosed the relationships and interests known to me for this scoped evidence review.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.conflict_declaration.has_conflict', false);

        $this->actingAs($verifier, 'api')
            ->get($downloadUrl)
            ->assertDownload('quality-proof-m8.pdf');

        $attestationUrl = '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
            . '/evidence/' . $evidence->getKey() . '/attestations';

        $this->actingAs($verifier, 'api')
            ->postJson($attestationUrl, [
                'verification_scope' => 'document_authenticity',
                'verification_method' => 'site_inspection',
                'outcome' => 'corroborated',
                'basis_summary' => 'The inspected quality record was corroborated against the documented observation criteria.',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error.code', 'verification_not_authorized');

        $this->actingAs($verifier, 'api')
            ->postJson($attestationUrl, [
                'verification_scope' => 'quality_observation',
                'verification_method' => 'site_inspection',
                'outcome' => 'corroborated',
                'basis_summary' => 'The inspected quality record was corroborated against the documented observation criteria.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.attestation.evidence_sha256', $evidence->sha256)
            ->assertJsonPath('data.attestation.outcome', 'corroborated');

        $this->actingAs($verifier, 'api')
            ->postJson($attestationUrl, [
                'verification_scope' => 'quality_observation',
                'verification_method' => 'site_inspection',
                'outcome' => 'corroborated',
                'basis_summary' => 'The inspected quality record was corroborated against the documented observation criteria.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'attestation_already_recorded');

        $attestation = SanctumScanIndependentAttestation::query()->firstOrFail();
        $this->assertSame($evidence->sha256, $attestation->evidence_sha256);
        $this->assertSame($verifier->getKey(), $attestation->verifier_user_id);
        $this->assertSame(0, SanctumScanExchangeEvidenceReview::query()->count());
        $this->assertSame('accepted', SanctumScanExchange::query()->findOrFail($fixture['exchange_id'])->status);
        $this->assertSame('open', SanctumScanExchangeDispute::query()->firstOrFail()->status);

        $this->actingAs($buyer, 'api')
            ->getJson('/api/user/sanctum/exchanges/' . $fixture['exchange_id'] . '/dispute/evidence')
            ->assertOk()
            ->assertJsonPath('data.evidence.0.independent_attestations.0.outcome', 'corroborated')
            ->assertJsonPath('data.evidence.0.independent_attestations.0.institution_name', 'Nakuru Independent Quality Institute');

        $this->assertTrue(SanctumScanVerifierRegistryEvent::query()->where('event_type', 'attestation_recorded')->exists());
        $this->assertTrue(SanctumScanExchangeEvent::query()->where('event_type', 'external_verifier_attestation_recorded')->exists());

        $this->actingAs($verifier, 'api')
            ->postJson(
                '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                    . '/evidence/' . $evidence->getKey() . '/conflict-declaration',
                [
                    'has_conflict' => true,
                    'disclosure_statement' => 'I have subsequently disclosed a material relationship relevant to this evidence review.',
                ]
            )
            ->assertCreated()
            ->assertJsonPath('data.conflict_declaration.has_conflict', true);

        $this->actingAs($verifier, 'api')
            ->get($downloadUrl)
            ->assertForbidden();

        $manager->setMandateStatus((int) $mandate->getKey(), $adminId, 'completed');
        $replacementMandate = $manager->grantMandate(
            (int) $evidence->getKey(),
            (int) $institution->getKey(),
            $adminId,
            'source_provenance',
            'Re-check the provenance metadata under a new, separate mandate.',
            now()->addDays(14)->toDateTimeString()
        );
        $this->assertInstanceOf(
            \App\Models\SanctumScanEvidenceVerificationMandate::class,
            $replacementMandate
        );
        $this->assertNotSame($mandate->getKey(), $replacementMandate->getKey());
        $this->assertSame('completed', $mandate->fresh()->status);
        $this->assertSame('quality_observation', $mandate->fresh()->verification_scope);
        $this->assertSame($mandate->getKey(), $attestation->fresh()->mandate_id);
        $this->assertSame('source_provenance', $replacementMandate->verification_scope);

        try {
            $attestation->update(['outcome' => 'not_corroborated']);
            $this->fail('Independent attestations should be immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame('Independent verifier attestations are immutable.', $exception->getMessage());
        }
    }

    public function test_a_dispute_participant_cannot_attest_to_their_own_evidence_as_an_independent_verifier(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('conflict-verify-seller', 'Conflict Verify Seller');
        $buyer = $this->makeBuyer('conflict-verify-buyer');
        $fixture = $this->makeDisputeEvidenceForVerification($merchant, $buyer, 'conflict');
        $adminId = $this->makeAdminForVerification('conflict');
        $manager = app(IndependentVerificationManager::class);

        $institution = $manager->registerInstitution($adminId, [
            'name' => 'Conflict Test Inspection Body',
            'organization_type' => 'inspection_body',
            'country_code' => 'KE',
        ]);
        $membership = $manager->inviteMember(
            (int) $institution->getKey(),
            (int) $buyer->getKey(),
            $adminId
        );

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey() . '/accept')
            ->assertOk();

        $manager->grantMandate(
            (int) $fixture['evidence']->getKey(),
            (int) $institution->getKey(),
            $adminId,
            'quality_observation',
            'This mandate is intentionally used to test a conflict restriction.',
            now()->addDays(7)->toDateTimeString()
        );

        $this->actingAs($buyer, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                . '/evidence/' . $fixture['evidence']->getKey() . '/attestations', [
                    'verification_scope' => 'quality_observation',
                    'verification_method' => 'document_review',
                    'outcome' => 'corroborated',
                    'basis_summary' => 'A participant must not certify their own disputed exchange evidence.',
                ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'verifier_conflict_of_interest');

        $this->assertSame(0, SanctumScanIndependentAttestation::query()->count());
    }

    public function test_exchange_participants_can_appeal_an_attestation_and_receive_an_immutable_process_decision(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('appeal-test-seller', 'Appeal Test Seller');
        $buyer = $this->makeBuyer('appeal-test-buyer');
        $verifier = $this->makeBuyer('appeal-test-verifier');
        $outsider = $this->makeBuyer('appeal-test-outsider');
        $fixture = $this->makeDisputeEvidenceForVerification($merchant, $buyer, 'appeal');
        $evidence = $fixture['evidence'];
        $exchangeId = (int) $fixture['exchange_id'];
        $adminId = $this->makeAdminForVerification('appeal');
        $manager = app(IndependentVerificationManager::class);

        $institution = $manager->registerInstitution($adminId, [
            'name' => 'Appeal Test Independent Institute',
            'organization_type' => 'inspection_body',
            'country_code' => 'KE',
        ]);
        $membership = $manager->inviteMember(
            (int) $institution->getKey(),
            (int) $verifier->getKey(),
            $adminId
        );

        $this->actingAs($verifier, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey() . '/accept')
            ->assertOk();

        $manager->recordCredentialReview(
            (int) $institution->getKey(),
            null,
            $adminId,
            'institution_registration',
            'business_registration',
            'Kenya Business Registration Service',
            'BRS-APPEAL-009',
            'KE',
            null,
            'registry_lookup',
            'verified',
            'Institution registration was checked against the referenced official record.',
            now()->addYear()->toDateTimeString()
        );

        $manager->recordCredentialReview(
            (int) $institution->getKey(),
            (int) $membership->getKey(),
            $adminId,
            'quality_observation',
            'professional_inspector',
            'Kenya Bureau of Standards',
            'KEBS-APPEAL-009',
            'KE',
            null,
            'issuer_confirmation',
            'verified',
            'The verifier qualification was checked for the required quality observation scope.',
            now()->addYear()->toDateTimeString()
        );

        $mandate = $manager->grantMandate(
            (int) $evidence->getKey(),
            (int) $institution->getKey(),
            $adminId,
            'quality_observation',
            'Review the submitted evidence for a scoped quality observation.',
            now()->addDays(7)->toDateTimeString()
        );

        $this->actingAs($verifier, 'api')
            ->postJson(
                '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                    . '/evidence/' . $evidence->getKey() . '/conflict-declaration',
                [
                    'has_conflict' => false,
                    'disclosure_statement' => 'I have disclosed the relationships and interests known to me for this assigned review.',
                ]
            )
            ->assertCreated();

        $attestationResponse = $this->actingAs($verifier, 'api')
            ->postJson(
                '/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                    . '/evidence/' . $evidence->getKey() . '/attestations',
                [
                    'verification_scope' => 'quality_observation',
                    'verification_method' => 'document_review',
                    'outcome' => 'corroborated',
                    'basis_summary' => 'The submitted material was corroborated against the stated quality observation criteria.',
                ]
            )
            ->assertCreated();

        $attestationId = (int) $attestationResponse->json('data.attestation.id');
        $attestation = SanctumScanIndependentAttestation::query()->findOrFail($attestationId);

        $buyerAppealPath = '/api/user/sanctum/exchanges/' . $exchangeId
            . '/dispute/evidence/' . $evidence->getKey()
            . '/attestations/' . $attestationId . '/appeals';

        $buyerAppeal = $this->actingAs($buyer, 'api')
            ->postJson($buyerAppealPath, [
                'reason_code' => 'methodology',
                'statement' => 'The review method did not account for a material chain-of-custody record.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.appeal.evidence_sha256', $evidence->sha256);

        $buyerAppealId = (int) $buyerAppeal->json('data.appeal.id');

        $this->actingAs($buyer, 'api')
            ->postJson($buyerAppealPath, [
                'reason_code' => 'methodology',
                'statement' => 'A duplicate appeal from the same participant must be rejected.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'appeal_already_exists');

        $merchantAppealPath = '/merchant-api/merchant/sanctum/exchanges/' . $exchangeId
            . '/dispute/evidence/' . $evidence->getKey()
            . '/attestations/' . $attestationId . '/appeals';

        $this->actingAs($merchant, 'merchant_api')
            ->postJson($merchantAppealPath, [
                'reason_code' => 'factual_error',
                'statement' => 'The attestation overlooked the merchant dispatch record attached to the exchange.',
            ])
            ->assertCreated();

        $this->actingAs($outsider, 'api')
            ->postJson($buyerAppealPath, [
                'reason_code' => 'other',
                'statement' => 'An unrelated account must not submit an appeal on this exchange.',
            ])
            ->assertForbidden();

        $this->assertSame(2, SanctumScanAttestationAppeal::query()->count());

        $decision = $manager->adjudicateAppeal(
            $buyerAppealId,
            $adminId,
            'correction_recommended',
            'The appeal identifies a material record that should be considered in a separate verifier correction process.'
        );
        $this->assertInstanceOf(SanctumScanAttestationAppealDecision::class, $decision);
        $this->assertSame('correction_recommended', $decision->outcome);
        $this->assertSame('corroborated', $attestation->fresh()->outcome);
        $this->assertSame($evidence->sha256, $attestation->fresh()->evidence_sha256);
        $this->assertSame('accepted', SanctumScanExchange::query()->findOrFail($exchangeId)->status);
        $this->assertSame('open', SanctumScanExchangeDispute::query()->firstOrFail()->status);
        $this->assertTrue(SanctumScanExchangeEvent::query()
            ->where('event_type', 'external_verifier_attestation_appealed')
            ->exists());
        $this->assertTrue(SanctumScanExchangeEvent::query()
            ->where('event_type', 'external_verifier_appeal_reviewed')
            ->exists());

        $this->assertSame(
            'appeal_already_decided',
            $manager->adjudicateAppeal(
                $buyerAppealId,
                $adminId,
                'dismissed',
                'The system must not permit a second decision on the same appeal.'
            )
        );

        try {
            $decision->update(['outcome' => 'dismissed']);
            $this->fail('Appeal decisions must be immutable.');
        } catch (\LogicException $exception) {
            $this->assertSame('Attestation appeal decisions are immutable.', $exception->getMessage());
        }

        $this->assertSame(1, SanctumScanAttestationAppealDecision::query()->count());
        $this->assertTrue(SanctumScanVerifierRegistryEvent::query()
            ->where('event_type', 'attestation_appeal_decided')
            ->exists());

        $this->actingAs($buyer, 'api')
            ->getJson('/api/user/sanctum/exchanges/' . $exchangeId . '/dispute/evidence')
            ->assertOk()
            ->assertJsonPath('data.evidence.0.independent_attestations.0.appeals.0.decision.outcome', 'correction_recommended');
    }

    public function test_suspending_an_institution_revokes_its_active_evidence_mandates(): void
    {
        Storage::fake('local');

        $merchant = $this->makeMerchant('suspend-verify-seller', 'Suspend Verify Seller');
        $buyer = $this->makeBuyer('suspend-verify-buyer');
        $verifier = $this->makeBuyer('suspend-quality-verifier');
        $fixture = $this->makeDisputeEvidenceForVerification($merchant, $buyer, 'suspend');
        $adminId = $this->makeAdminForVerification('suspend');
        $manager = app(IndependentVerificationManager::class);

        $institution = $manager->registerInstitution($adminId, [
            'name' => 'Suspension Test Institute',
            'organization_type' => 'research_institute',
            'country_code' => 'KE',
        ]);
        $membership = $manager->inviteMember(
            (int) $institution->getKey(),
            (int) $verifier->getKey(),
            $adminId
        );

        $this->actingAs($verifier, 'api')
            ->postJson('/api/user/sanctum/verifier/memberships/' . $membership->getKey() . '/accept')
            ->assertOk();

        $mandate = $manager->grantMandate(
            (int) $fixture['evidence']->getKey(),
            (int) $institution->getKey(),
            $adminId,
            'quality_observation',
            'Inspect the evidence within the limited quality observation scope.',
            now()->addDays(7)->toDateTimeString()
        );
        $this->assertInstanceOf(
            \App\Models\SanctumScanEvidenceVerificationMandate::class,
            $mandate
        );

        $manager->setInstitutionStatus((int) $institution->getKey(), $adminId, 'suspended');

        $this->assertSame('revoked', $mandate->fresh()->status);

        $this->actingAs($verifier, 'api')
            ->get('/api/user/sanctum/verifier/memberships/' . $membership->getKey()
                . '/evidence/' . $fixture['evidence']->getKey() . '/download')
            ->assertForbidden();

        $this->assertSame(0, SanctumScanIndependentAttestation::query()->count());
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('sanctum_scan_attestation_revisions');
        Schema::dropIfExists('sanctum_scan_attestation_appeal_decisions');
        Schema::dropIfExists('sanctum_scan_attestation_appeals');
        Schema::dropIfExists('sanctum_scan_verifier_conflict_declarations');
        Schema::dropIfExists('sanctum_scan_verifier_credential_reviews');
        Schema::dropIfExists('sanctum_scan_verifier_registry_events');
        Schema::dropIfExists('sanctum_scan_independent_attestations');
        Schema::dropIfExists('sanctum_scan_evidence_verification_mandates');
        Schema::dropIfExists('sanctum_scan_verifier_memberships');
        Schema::dropIfExists('sanctum_scan_verifier_institutions');
        Schema::dropIfExists('sanctum_scan_exchange_evidence_reviews');
        Schema::dropIfExists('sanctum_scan_exchange_dispute_evidence');
        Schema::dropIfExists('sanctum_scan_exchange_dispute_messages');
        Schema::dropIfExists('sanctum_scan_exchange_disputes');
        Schema::dropIfExists('sanctum_scan_exchange_payment_evidence');
        Schema::dropIfExists('sanctum_scan_exchange_events');
        Schema::dropIfExists('sanctum_scan_exchanges');
        Schema::dropIfExists('sanctum_scan_resources');
        Schema::dropIfExists('merchants');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('users');
        DB::purge('sqlite');

        parent::tearDown();
    }
}
