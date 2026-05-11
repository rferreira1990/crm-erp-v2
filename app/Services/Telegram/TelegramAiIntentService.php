<?php

namespace App\Services\Telegram;

use App\DTO\Telegram\TelegramAiIntentData;
use App\Models\Company;
use App\Models\TelegramUserLink;
use App\Services\Ai\AiExecutionService;
use App\Services\Ai\AiUsageBudgetService;
use App\Services\Ai\AiUsageLoggerService;
use Illuminate\Support\Str;

class TelegramAiIntentService
{
    public function __construct(
        private readonly AiExecutionService $executionService,
        private readonly AiUsageBudgetService $budgetService,
        private readonly AiUsageLoggerService $usageLoggerService
    ) {
    }

    public function detect(string $text, TelegramUserLink $link): TelegramAiIntentData
    {
        $normalizedText = trim($text);
        $localIntent = $this->detectLocalIntent($normalizedText);
        if ($localIntent instanceof TelegramAiIntentData) {
            return $localIntent;
        }

        $company = Company::query()->whereKey((int) $link->company_id)->firstOrFail();

        $this->budgetService->ensureCanUseAi($company);

        $prompt = $this->buildIntentPrompt($normalizedText);

        $responseData = $this->executionService->executePrompt($prompt);
        $intentData = $this->parseIntentResponse($responseData->text);

        $this->usageLoggerService->log(
            companyId: (int) $company->id,
            userId: $link->user_id ? (int) $link->user_id : null,
            source: 'telegram_ai_intent',
            responseData: $responseData,
            metadata: [
                'telegram_user_id' => (int) $link->telegram_user_id,
                'prompt_length' => mb_strlen($normalizedText),
                'detected_intent' => $intentData->intent,
            ]
        );

        return $intentData;
    }

    private function buildIntentPrompt(string $inputText): string
    {
        return implode("\n", [
            'Classifica a intencao desta mensagem do Telegram.',
            'Responde apenas JSON valido numa unica linha.',
            'Formato obrigatorio: {"intent":"stock_lookup|pending_quotes_lookup|quote_info_lookup|customer_quotes_lookup|customer_balance_lookup|supplier_balance_lookup|kpi_lookup|overdue_customers_lookup|overdue_suppliers_lookup|quotes_followup_lookup|create_construction_site_daily_log|calendar_event_create|send_email_start|unknown","term":"string|null","site_term":"string|null","description":"string|null","data":{}|null,"confidence":0.0}',
            'Regras:',
            '- stock_lookup: consulta de stock de artigo/produto.',
            '- pending_quotes_lookup: pedidos sobre orcamentos por responder/aguardar resposta.',
            '- quote_info_lookup: pedidos de informacao de um orcamento especifico.',
            '- customer_quotes_lookup: pedidos para listar orcamentos de um cliente especifico.',
            '- customer_balance_lookup: pedidos de saldo/divida de cliente.',
            '- supplier_balance_lookup: pedidos de saldo/divida/pagamentos de fornecedor.',
            '- kpi_lookup: pedidos de KPI financeiro/comercial (hoje ou mes). Term pode ser hoje|mes, default hoje.',
            '- overdue_customers_lookup: pedidos de clientes com documentos vencidos.',
            '- overdue_suppliers_lookup: pedidos de fornecedores com documentos vencidos.',
            '- quotes_followup_lookup: pedidos de orcamentos sem resposta para follow-up.',
            '- create_construction_site_daily_log: criar registo diario de obra com termo da obra e descricao do trabalho.',
            '- calendar_event_create: pedido para criar tarefa/evento na agenda.',
            '- send_email_start: pedido claro para enviar email, com destinatario no campo term.',
            '- Quando o intent exigir pesquisa por nome/numero, preencher term.',
            '- Para create_construction_site_daily_log preencher site_term e description.',
            '- Para calendar_event_create preencher data com as chaves: title, description, type, starts_at_text, ends_at_text, date_text, time_text, customer_term, supplier_term, construction_site_term, assigned_user_term, priority, all_day.',
            '- Em calendar_event_create, tenta extrair hora de formatos como: 10h, 10:30, 8h30, das 08:30 as 12h.',
            '- Se o utilizador disser "dia todo", definir all_day=true e time_text pode ser null.',
            '- Considera tambem expressoes: "depois do almoco", "fim da tarde", "daqui a 2 dias", "proxima terca", "terca que vem".',
            '- Para outros intents, site_term, description e data devem ser null.',
            '- Quando nao houver termo identificavel, usar term = null.',
            '- Se nao se enquadrar, usa intent = unknown e term = null.',
            '- Nao respondas ao utilizador.',
            '- Nao inventes dados.',
            'Mensagem: '.$inputText,
        ]);
    }

    private function parseIntentResponse(string $responseText): TelegramAiIntentData
    {
        $json = $this->extractJsonPayload($responseText);
        if ($json === null) {
            return TelegramAiIntentData::unknown();
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return TelegramAiIntentData::unknown();
        }

        $intent = is_string($decoded['intent'] ?? null)
            ? trim((string) $decoded['intent'])
            : TelegramAiIntentData::INTENT_UNKNOWN;

        if (! in_array($intent, [
            TelegramAiIntentData::INTENT_STOCK_LOOKUP,
            TelegramAiIntentData::INTENT_PENDING_QUOTES_LOOKUP,
            TelegramAiIntentData::INTENT_QUOTE_INFO_LOOKUP,
            TelegramAiIntentData::INTENT_CUSTOMER_QUOTES_LOOKUP,
            TelegramAiIntentData::INTENT_CUSTOMER_BALANCE_LOOKUP,
            TelegramAiIntentData::INTENT_SUPPLIER_BALANCE_LOOKUP,
            TelegramAiIntentData::INTENT_KPI_LOOKUP,
            TelegramAiIntentData::INTENT_OVERDUE_CUSTOMERS_LOOKUP,
            TelegramAiIntentData::INTENT_OVERDUE_SUPPLIERS_LOOKUP,
            TelegramAiIntentData::INTENT_QUOTES_FOLLOWUP_LOOKUP,
            TelegramAiIntentData::INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG,
            TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
            TelegramAiIntentData::INTENT_SEND_EMAIL_START,
            TelegramAiIntentData::INTENT_UNKNOWN,
        ], true)) {
            $intent = TelegramAiIntentData::INTENT_UNKNOWN;
        }

        $term = is_string($decoded['term'] ?? null) ? trim((string) $decoded['term']) : '';
        $term = $term !== '' ? $term : null;
        $siteTerm = is_string($decoded['site_term'] ?? null) ? trim((string) $decoded['site_term']) : '';
        $siteTerm = $siteTerm !== '' ? $siteTerm : null;
        $description = is_string($decoded['description'] ?? null) ? trim((string) $decoded['description']) : '';
        $description = $description !== '' ? $description : null;
        $calendarData = $this->parseCalendarIntentData($decoded['data'] ?? null);

        $confidence = null;
        if (is_numeric($decoded['confidence'] ?? null)) {
            $confidence = (float) $decoded['confidence'];
            $confidence = max(0.0, min(1.0, $confidence));
        }

        if (in_array($intent, [
            TelegramAiIntentData::INTENT_STOCK_LOOKUP,
            TelegramAiIntentData::INTENT_QUOTE_INFO_LOOKUP,
            TelegramAiIntentData::INTENT_CUSTOMER_QUOTES_LOOKUP,
            TelegramAiIntentData::INTENT_CUSTOMER_BALANCE_LOOKUP,
            TelegramAiIntentData::INTENT_SUPPLIER_BALANCE_LOOKUP,
            TelegramAiIntentData::INTENT_SEND_EMAIL_START,
        ], true) && $term === null) {
            return TelegramAiIntentData::unknown();
        }

        if ($intent === TelegramAiIntentData::INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG) {
            if ($siteTerm === null || $description === null || mb_strlen($description) < 10) {
                return TelegramAiIntentData::unknown();
            }
            $calendarData = null;
        } elseif ($intent === TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE) {
            if (! is_array($calendarData) || $calendarData === []) {
                return TelegramAiIntentData::unknown();
            }
            $siteTerm = null;
            $description = null;
        } else {
            $siteTerm = null;
            $description = null;
            $calendarData = null;
        }

        return new TelegramAiIntentData(
            intent: $intent,
            term: $term,
            confidence: $confidence,
            siteTerm: $siteTerm,
            description: $description,
            data: $calendarData
        );
    }

    private function detectLocalIntent(string $text): ?TelegramAiIntentData
    {
        $normalized = Str::of($text)->lower()->ascii()->squish()->value();
        if ($normalized === '') {
            return null;
        }

        if (
            preg_match('/\b(agendar|marca|marcar|lembra-me|lembrete|cria tarefa|criar tarefa)\b/u', $normalized) === 1
            && (
                str_contains($normalized, 'amanha')
                || str_contains($normalized, 'hoje')
                || str_contains($normalized, 'proxima')
                || str_contains($normalized, 'segunda')
                || str_contains($normalized, 'terca')
                || str_contains($normalized, 'quarta')
                || str_contains($normalized, 'quinta')
                || str_contains($normalized, 'sexta')
                || str_contains($normalized, 'sabado')
                || str_contains($normalized, 'domingo')
                || preg_match('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/u', $normalized) === 1
            )
        ) {
            $type = match (true) {
                str_contains($normalized, 'visita') => 'visita',
                str_contains($normalized, 'reuniao') || str_contains($normalized, 'reuniao') => 'reuniao',
                str_contains($normalized, 'obra') || str_contains($normalized, 'instal') => 'obra',
                str_contains($normalized, 'lembra') || str_contains($normalized, 'lembrete') => 'lembrete',
                default => 'tarefa',
            };

            return new TelegramAiIntentData(
                intent: TelegramAiIntentData::INTENT_CALENDAR_EVENT_CREATE,
                term: null,
                confidence: 0.80,
                data: [
                    'title' => null,
                    'description' => null,
                    'type' => $type,
                    'starts_at_text' => null,
                    'ends_at_text' => null,
                    'date_text' => null,
                    'time_text' => null,
                    'customer_term' => null,
                    'supplier_term' => null,
                    'construction_site_term' => null,
                    'assigned_user_term' => null,
                    'priority' => 'normal',
                    'all_day' => null,
                ]
            );
        }

        return null;
    }

    /**
     * @param mixed $rawData
     * @return array{
     *   title:string|null,
     *   description:string|null,
     *   type:string|null,
     *   starts_at_text:string|null,
     *   ends_at_text:string|null,
     *   date_text:string|null,
     *   time_text:string|null,
     *   customer_term:string|null,
     *   supplier_term:string|null,
     *   construction_site_term:string|null,
     *   assigned_user_term:string|null,
     *   priority:string|null,
     *   all_day:bool|null
     * }|null
     */
    private function parseCalendarIntentData(mixed $rawData): ?array
    {
        if (! is_array($rawData)) {
            return null;
        }

        return [
            'title' => $this->sanitizeNullableString($rawData['title'] ?? null),
            'description' => $this->sanitizeNullableString($rawData['description'] ?? null),
            'type' => $this->sanitizeNullableString($rawData['type'] ?? null),
            'starts_at_text' => $this->sanitizeNullableString($rawData['starts_at_text'] ?? null),
            'ends_at_text' => $this->sanitizeNullableString($rawData['ends_at_text'] ?? null),
            'date_text' => $this->sanitizeNullableString($rawData['date_text'] ?? null),
            'time_text' => $this->sanitizeNullableString($rawData['time_text'] ?? null),
            'customer_term' => $this->sanitizeNullableString($rawData['customer_term'] ?? null),
            'supplier_term' => $this->sanitizeNullableString($rawData['supplier_term'] ?? null),
            'construction_site_term' => $this->sanitizeNullableString($rawData['construction_site_term'] ?? null),
            'assigned_user_term' => $this->sanitizeNullableString($rawData['assigned_user_term'] ?? null),
            'priority' => $this->sanitizeNullableString($rawData['priority'] ?? null),
            'all_day' => $this->sanitizeNullableBool($rawData['all_day'] ?? null),
        ];
    }

    private function sanitizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function sanitizeNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if (in_array($normalized, ['true', '1', 'sim', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'nao', 'não', 'no'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return null;
    }

    private function extractJsonPayload(string $raw): ?string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (str_starts_with($trimmed, '```')) {
            if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/si', $trimmed, $matches) === 1) {
                $trimmed = trim((string) ($matches[1] ?? ''));
            }
        }

        if (! str_starts_with($trimmed, '{') || ! str_ends_with($trimmed, '}')) {
            return null;
        }

        return $trimmed;
    }
}
