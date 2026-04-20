<?php

namespace Tests\Feature\Admin;

use App\Mail\Admin\QuoteSentMail;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuoteSentMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_quote_email_renders_professional_html_with_branding_and_custom_message(): void
    {
        Storage::fake('local');

        $company = $this->createCompany('Empresa Comercial');
        $customer = $this->createCustomer($company, 'Cliente Exemplo');
        $assignedUser = User::factory()->create([
            'company_id' => $company->id,
            'is_super_admin' => false,
            'is_active' => true,
        ]);

        $quote = Quote::createWithGeneratedNumber($company->id, [
            'version' => 1,
            'status' => Quote::STATUS_SENT,
            'customer_id' => $customer->id,
            'issue_date' => '2026-04-14',
            'valid_until' => '2026-04-30',
            'grand_total' => 1234.56,
            'currency' => 'EUR',
            'assigned_user_id' => $assignedUser->id,
            'is_active' => true,
            'is_locked' => false,
            'pdf_path' => 'quotes/'.$company->id.'/ORC-2026-0001.pdf',
        ]);

        Storage::disk('local')->put((string) $quote->pdf_path, 'pdf-content');
        Setting::put('mail.logo_url', 'https://cdn.example.test/logo.png');

        $mail = new QuoteSentMail(
            quote: $quote,
            subjectLine: 'Proposta Comercial '.$quote->number.' - '.$company->name,
            messageBody: "Mensagem personalizada\ncom duas linhas."
        );

        $mail->assertHasSubject('Proposta Comercial '.$quote->number.' - '.$company->name);
        $mail->assertSeeInHtml('Proposta Comercial');
        $mail->assertSeeInHtml('https://cdn.example.test/logo.png', false);
        $mail->assertSeeInHtml($quote->number);
        $mail->assertSeeInHtml('14/04/2026');
        $mail->assertSeeInHtml('30/04/2026');
        $mail->assertSeeInHtml('1.234,56 EUR');
        $mail->assertSeeInHtml('Mensagem personalizada');
        $mail->assertSeeInText('Mensagem personalizada');
        $attachments = $mail->attachments();
        $this->assertCount(1, $attachments);
        $this->assertSame('ORC-2026-0001.pdf', $attachments[0]->as);
        $attachmentContent = $attachments[0]->attachWith(
            fn (string $path) => '',
            fn (callable $data, $attachment) => $data()
        );
        $this->assertSame('pdf-content', $attachmentContent);
    }

    public function test_quote_email_fallback_without_logo_keeps_company_identity_and_default_subject(): void
    {
        Setting::forgetByPrefix('mail.logo_url');

        $company = $this->createCompany('Empresa Sem Logo');
        $customer = $this->createCustomer($company, 'Cliente Sem Logo');
        $quote = Quote::createWithGeneratedNumber($company->id, [
            'version' => 1,
            'status' => Quote::STATUS_DRAFT,
            'customer_id' => $customer->id,
            'issue_date' => '2026-04-14',
            'valid_until' => null,
            'grand_total' => 90,
            'currency' => 'EUR',
            'is_active' => true,
            'is_locked' => false,
        ]);

        $mail = new QuoteSentMail(
            quote: $quote,
            subjectLine: '',
            messageBody: null
        );

        $mail->assertHasSubject(QuoteSentMail::defaultSubjectForQuote($quote));
        $mail->assertDontSeeInHtml('<img', false);
        $mail->assertSeeInHtml('Empresa Sem Logo');
        $mail->assertSeeInHtml('Estamos disponiveis para qualquer esclarecimento adicional.');
        $mail->assertSeeInText('Com os melhores cumprimentos');
    }

    private function createCompany(string $name): Company
    {
        return Company::query()->create([
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'email' => Str::slug($name).'@empresa.test',
            'phone' => '+351210000000',
            'is_active' => true,
        ]);
    }

    private function createCustomer(Company $company, string $name): Customer
    {
        return Customer::query()->create([
            'company_id' => $company->id,
            'customer_type' => Customer::TYPE_COMPANY,
            'name' => $name,
            'email' => Str::slug($name).'@cliente.test',
            'is_active' => true,
        ]);
    }
}
