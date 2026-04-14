<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\QuoteSentMail;
use App\Models\Article;
use App\Models\Category;
use App\Models\Company;
use App\Models\CompanyPaymentTermOverride;
use App\Models\CompanyVatExemptionReasonOverride;
use App\Models\CompanyVatRateOverride;
use App\Models\Customer;
use App\Models\PaymentMethod;
use App\Models\PaymentTerm;
use App\Models\PriceTier;
use App\Models\ProductFamily;
use App\Models\Quote;
use App\Models\Unit;
use App\Models\User;
use App\Models\VatExemptionReason;
use App\Models\VatRate;
use Database\Seeders\InitialSaasSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuotesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSaasSeeder::class);
    }

    public function test_multi_tenant_access_to_quotes_is_isolated(): void
    {
        $companyA = $this->createCompany('Empresa Orcamentos A');
        $companyB = $this->createCompany('Empresa Orcamentos B');
        $adminA = $this->createCompanyUser($companyA, User::ROLE_COMPANY_ADMIN);

        $quoteA = $this->createQuoteForCompany($companyA, 'Cliente A');
        $quoteB = $this->createQuoteForCompany($companyB, 'Cliente B');

        $response = $this->actingAs($adminA)->get(route('admin.quotes.index'));
        $response->assertOk();
        $response->assertSee('Cliente A');
        $response->assertDontSee('Cliente B');

        $this->actingAs($adminA)->get(route('admin.quotes.show', $quoteB->id))->assertNotFound();
        $this->actingAs($adminA)->get(route('admin.quotes.edit', $quoteB->id))->assertNotFound();
        $this->actingAs($adminA)->patch(route('admin.quotes.update', $quoteB->id), [
            'customer_id' => $quoteA->customer_id,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [
                [
                    'line_type' => 'text',
                    'description' => 'Linha',
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertNotFound();
    }

    public function test_user_without_permissions_cannot_manage_quotes(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Sem Perm');
        $user = $this->createCompanyUser($company, User::ROLE_COMPANY_USER);
        $quote = $this->createQuoteForCompany($company, 'Cliente Sem Perm');

        $this->actingAs($user)->get(route('admin.quotes.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.quotes.create'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.quotes.show', $quote->id))->assertForbidden();
        $this->actingAs($user)->post(route('admin.quotes.store'), [
            'customer_id' => $quote->customer_id,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [
                [
                    'line_type' => 'text',
                    'description' => 'Linha',
                    'quantity' => 1,
                    'unit_price' => 10,
                ],
            ],
        ])->assertForbidden();
    }

    public function test_quote_create_generates_number_and_applies_defaults(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Create');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Create');
        $article = $this->createArticle($company, 'Artigo Q', 100);
        $priceTier = PriceTier::query()
            ->visibleToCompany($company->id)
            ->where('is_system', true)
            ->where('is_default', true)
            ->firstOrFail();

        $response = $this->actingAs($admin)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-04-13',
            'is_active' => 1,
            'items' => [
                [
                    'line_type' => 'article',
                    'article_id' => $article->id,
                    'quantity' => 1,
                ],
            ],
        ]);

        $response->assertRedirect();

        $quote = Quote::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('ORC-2026-0001', $quote->number);
        $this->assertSame(Quote::STATUS_DRAFT, $quote->status);
        $this->assertSame($priceTier->id, (int) $quote->price_tier_id);
        $this->assertDatabaseHas('quote_items', [
            'quote_id' => $quote->id,
            'company_id' => $company->id,
        ]);
    }

    public function test_quote_number_sequence_is_per_company_and_per_year(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Sequencia');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Sequencia');

        $this->actingAs($admin)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-01-10',
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [['line_type' => 'text', 'description' => 'L1', 'quantity' => 1, 'unit_price' => 10]],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2026-02-10',
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [['line_type' => 'text', 'description' => 'L2', 'quantity' => 1, 'unit_price' => 20]],
        ])->assertRedirect();

        $this->actingAs($admin)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id,
            'issue_date' => '2027-01-10',
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [['line_type' => 'text', 'description' => 'L3', 'quantity' => 1, 'unit_price' => 30]],
        ])->assertRedirect();

        $numbers = Quote::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->pluck('number')
            ->all();

        $this->assertSame(['ORC-2026-0001', 'ORC-2026-0002', 'ORC-2027-0001'], $numbers);
    }

    public function test_quote_line_calculation_and_totals_are_correct(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Totais');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente Totais');
        $vat23 = $this->mainland23Rate();

        $response = $this->actingAs($admin)->post(route('admin.quotes.store'), [
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [
                [
                    'line_type' => 'text',
                    'description' => 'Servico',
                    'quantity' => 2,
                    'unit_price' => 100,
                    'discount_percent' => 10,
                    'vat_rate_id' => $vat23->id,
                ],
            ],
        ]);

        $response->assertRedirect();

        $quote = Quote::query()->where('company_id', $company->id)->firstOrFail();
        $this->assertSame('200.00', (string) $quote->subtotal);
        $this->assertSame('20.00', (string) $quote->discount_total);
        $this->assertSame('41.40', (string) $quote->tax_total);
        $this->assertSame('221.40', (string) $quote->grand_total);
    }

    public function test_exempt_vat_requires_reason_and_non_exempt_rejects_reason(): void
    {
        $company = $this->createCompany('Empresa Orcamentos IVA');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $customer = $this->createCustomer($company, 'Cliente IVA');
        $exemptRate = VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'Isento')
            ->firstOrFail();
        $reason = VatExemptionReason::query()->where('code', 'M07')->firstOrFail();
        $rate23 = $this->mainland23Rate();

        CompanyVatRateOverride::query()->create([
            'company_id' => $company->id,
            'vat_rate_id' => $exemptRate->id,
            'is_enabled' => true,
        ]);
        CompanyVatExemptionReasonOverride::query()->create([
            'company_id' => $company->id,
            'vat_exemption_reason_id' => $reason->id,
            'is_enabled' => true,
        ]);

        $missingReason = $this->actingAs($admin)
            ->from(route('admin.quotes.create'))
            ->post(route('admin.quotes.store'), [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'currency' => 'EUR',
                'is_active' => 1,
                'items' => [
                    [
                        'line_type' => 'text',
                        'description' => 'Linha isenta',
                        'quantity' => 1,
                        'unit_price' => 100,
                        'vat_rate_id' => $exemptRate->id,
                    ],
                ],
            ]);

        $missingReason->assertRedirect(route('admin.quotes.create'));
        $missingReason->assertSessionHasErrors(['items.0.vat_exemption_reason_id']);

        $invalidReason = $this->actingAs($admin)
            ->from(route('admin.quotes.create'))
            ->post(route('admin.quotes.store'), [
                'customer_id' => $customer->id,
                'issue_date' => now()->toDateString(),
                'currency' => 'EUR',
                'is_active' => 1,
                'items' => [
                    [
                        'line_type' => 'text',
                        'description' => 'Linha normal',
                        'quantity' => 1,
                        'unit_price' => 100,
                        'vat_rate_id' => $rate23->id,
                        'vat_exemption_reason_id' => $reason->id,
                    ],
                ],
            ]);

        $invalidReason->assertRedirect(route('admin.quotes.create'));
        $invalidReason->assertSessionHasErrors(['items.0.vat_exemption_reason_id']);
    }

    public function test_status_transitions_and_edit_lock_are_enforced(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Status');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $quote = $this->createQuoteForCompany($company, 'Cliente Status');

        $this->actingAs($admin)->post(route('admin.quotes.status.change', $quote->id), [
            'status' => Quote::STATUS_SENT,
        ])->assertRedirect(route('admin.quotes.show', $quote->id));

        $backToDraft = $this->actingAs($admin)
            ->from(route('admin.quotes.show', $quote->id))
            ->post(route('admin.quotes.status.change', $quote->id), [
                'status' => Quote::STATUS_DRAFT,
            ]);
        $backToDraft->assertRedirect(route('admin.quotes.show', $quote->id));
        $backToDraft->assertSessionHasErrors('status');

        $this->actingAs($admin)->post(route('admin.quotes.status.change', $quote->id), [
            'status' => Quote::STATUS_APPROVED,
        ])->assertRedirect(route('admin.quotes.show', $quote->id));

        $quote->refresh();
        $this->assertSame(Quote::STATUS_APPROVED, $quote->status);
        $this->assertTrue($quote->is_locked);

        $update = $this->actingAs($admin)->patch(route('admin.quotes.update', $quote->id), [
            'customer_id' => $quote->customer_id,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [
                ['line_type' => 'text', 'description' => 'Linha', 'quantity' => 1, 'unit_price' => 1],
            ],
        ]);
        $update->assertRedirect(route('admin.quotes.show', $quote->id));
        $update->assertSessionHasErrors('quote');
    }

    public function test_pdf_generation_and_email_send_update_quote_tracking(): void
    {
        Storage::fake('local');
        Mail::fake();

        $company = $this->createCompany('Empresa Orcamentos PDF Email');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $quote = $this->createQuoteForCompany($company, 'Cliente PDF Email');

        $this->actingAs($admin)->post(route('admin.quotes.pdf.generate', $quote->id))
            ->assertRedirect(route('admin.quotes.show', $quote->id));

        $quote->refresh();
        $this->assertNotNull($quote->pdf_path);
        Storage::disk('local')->assertExists($quote->pdf_path);

        $this->actingAs($admin)->post(route('admin.quotes.email.send', $quote->id), [
            'to' => 'cliente@example.test',
            'subject' => 'Orcamento '.$quote->number,
            'message' => 'Segue anexo.',
        ])->assertRedirect(route('admin.quotes.show', $quote->id));

        Mail::assertSent(QuoteSentMail::class);

        $quote->refresh();
        $this->assertSame('cliente@example.test', $quote->email_last_sent_to);
        $this->assertNotNull($quote->email_last_sent_at);
        $this->assertSame(Quote::STATUS_SENT, $quote->status);
    }

    public function test_quote_duplication_creates_new_draft_with_copied_lines(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Duplicar');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $quote = $this->createQuoteForCompany($company, 'Cliente Duplicar');

        $this->actingAs($admin)->post(route('admin.quotes.duplicate', $quote->id))
            ->assertRedirect();

        $quotes = Quote::query()
            ->where('company_id', $company->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $quotes);
        $original = $quotes[0];
        $duplicate = $quotes[1];

        $this->assertNotSame($original->number, $duplicate->number);
        $this->assertSame(Quote::STATUS_DRAFT, $duplicate->status);
        $this->assertSame($original->items()->count(), $duplicate->items()->count());
    }

    public function test_edit_keeps_historical_selected_values_and_update_accepts_them(): void
    {
        $company = $this->createCompany('Empresa Orcamentos Historico');
        $admin = $this->createCompanyUser($company, User::ROLE_COMPANY_ADMIN);
        $quote = $this->createQuoteForCompany($company, 'Cliente Historico');

        $contact = $quote->customer->contacts()->create([
            'company_id' => $company->id,
            'name' => 'Contacto Historico',
            'email' => 'historico@example.test',
            'is_primary' => true,
        ]);

        $inactiveTier = PriceTier::query()->create([
            'company_id' => $company->id,
            'name' => 'Escalao Inativo',
            'percentage_adjustment' => -5,
            'is_system' => false,
            'is_default' => false,
            'is_active' => false,
        ]);

        $hiddenTerm = PaymentTerm::query()
            ->visibleToCompany($company->id)
            ->orderBy('id')
            ->firstOrFail();

        CompanyPaymentTermOverride::query()->create([
            'company_id' => $company->id,
            'payment_term_id' => $hiddenTerm->id,
            'is_enabled' => false,
        ]);

        $disabledVat = $this->mainland23Rate();
        CompanyVatRateOverride::query()->create([
            'company_id' => $company->id,
            'vat_rate_id' => $disabledVat->id,
            'is_enabled' => false,
        ]);

        $quote->forceFill([
            'customer_contact_id' => $contact->id,
            'price_tier_id' => $inactiveTier->id,
            'payment_term_id' => $hiddenTerm->id,
            'default_vat_rate_id' => $disabledVat->id,
        ])->save();

        $quote->customer->forceFill(['is_active' => false])->save();
        $quote->items()->update(['vat_rate_id' => $disabledVat->id]);

        $editResponse = $this->actingAs($admin)->get(route('admin.quotes.edit', $quote->id));
        $editResponse->assertOk();
        $editResponse->assertSee('Cliente Historico');
        $editResponse->assertSee('Contacto Historico');
        $editResponse->assertSee('Escalao Inativo');
        $editResponse->assertSee($hiddenTerm->name);

        $updateResponse = $this->actingAs($admin)->patch(route('admin.quotes.update', $quote->id), [
            'customer_id' => $quote->customer_id,
            'customer_contact_id' => $contact->id,
            'issue_date' => now()->toDateString(),
            'price_tier_id' => $inactiveTier->id,
            'payment_term_id' => $hiddenTerm->id,
            'default_vat_rate_id' => $disabledVat->id,
            'currency' => 'EUR',
            'is_active' => 1,
            'items' => [
                [
                    'line_type' => 'text',
                    'description' => 'Linha historica',
                    'quantity' => 1,
                    'unit_price' => 100,
                    'vat_rate_id' => $disabledVat->id,
                ],
            ],
        ]);

        $updateResponse->assertRedirect(route('admin.quotes.edit', $quote->id));
        $updateResponse->assertSessionHasNoErrors();
    }

    private function createQuoteForCompany(Company $company, string $customerName): Quote
    {
        $customer = $this->createCustomer($company, $customerName);
        $article = $this->createArticle($company, 'Artigo '.$customerName, 100);

        $quote = Quote::createWithGeneratedNumber($company->id, [
            'version' => 1,
            'status' => Quote::STATUS_DRAFT,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'currency' => 'EUR',
            'is_active' => true,
            'is_locked' => false,
            'subtotal' => 0,
            'discount_total' => 0,
            'tax_total' => 0,
            'grand_total' => 0,
        ]);

        $quote->items()->create([
            'company_id' => $company->id,
            'sort_order' => 1,
            'line_type' => 'article',
            'article_id' => $article->id,
            'description' => $article->designation,
            'quantity' => 1,
            'unit_id' => $article->unit_id,
            'unit_price' => 100,
            'discount_percent' => 0,
            'vat_rate_id' => $this->mainland23Rate()->id,
            'subtotal' => 100,
            'discount_amount' => 0,
            'tax_amount' => 23,
            'total' => 123,
        ]);

        $quote->recalculateTotals();
        $quote->addStatusLog(Quote::STATUS_DRAFT, null, 'Orcamento criado.', null);

        return $quote;
    }

    private function createCustomer(Company $company, string $name): Customer
    {
        /** @var PriceTier $priceTier */
        $priceTier = PriceTier::query()
            ->visibleToCompany($company->id)
            ->where('is_system', true)
            ->where('is_default', true)
            ->firstOrFail();

        /** @var PaymentTerm $paymentTerm */
        $paymentTerm = PaymentTerm::query()
            ->visibleToCompany($company->id)
            ->orderBy('id')
            ->firstOrFail();

        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'price_tier_id' => $priceTier->id,
            'payment_term_id' => $paymentTerm->id,
            'default_vat_rate_id' => $this->mainland23Rate()->id,
            'is_active' => true,
        ]);
    }

    private function createArticle(Company $company, string $designation, float $salePrice): Article
    {
        $family = ProductFamily::query()->create([
            'company_id' => $company->id,
            'is_system' => false,
            'name' => 'Familia '.Str::random(4),
            'family_code' => (string) random_int(10, 99),
        ]);

        return Article::createWithGeneratedCode($company->id, [
            'designation' => $designation,
            'product_family_id' => $family->id,
            'category_id' => $this->defaultCategoryId(),
            'unit_id' => $this->defaultUnitId(),
            'vat_rate_id' => $this->mainland23Rate()->id,
            'sale_price' => $salePrice,
            'moves_stock' => false,
            'stock_alert_enabled' => false,
            'is_active' => true,
        ]);
    }

    private function mainland23Rate(): VatRate
    {
        return VatRate::query()
            ->where('region', VatRate::REGION_MAINLAND)
            ->where('name', 'IVA 23%')
            ->firstOrFail();
    }

    private function defaultCategoryId(): int
    {
        return (int) Category::query()->whereRaw('LOWER(name) = ?', ['produto'])->value('id');
    }

    private function defaultUnitId(): int
    {
        return (int) Unit::query()->where('code', 'UN')->value('id');
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'is_active' => true,
        ]);
    }

    private function createCompanyUser(
        Company $company,
        string $role,
        bool $isActive = true,
        ?string $email = null
    ): User {
        $user = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => $isActive,
            'email' => $email ?? Str::lower(Str::random(8)).'@example.test',
        ]);

        $user->syncRoles([$role]);

        return $user;
    }
}
