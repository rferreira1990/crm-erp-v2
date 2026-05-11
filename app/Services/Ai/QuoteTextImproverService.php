<?php

namespace App\Services\Ai;

use App\Exceptions\Ai\AiBudgetExceededException;
use App\Models\User;
use RuntimeException;

class QuoteTextImproverService
{
    public function __construct(
        private readonly AiExecutionService $executionService,
        private readonly AiUsageLoggerService $usageLoggerService,
        private readonly AiUsageBudgetService $usageBudgetService
    ) {
    }

    /**
     * @return array{
     *   improved_text:string,
     *   model:string,
     *   input_tokens:int|null,
     *   output_tokens:int|null,
     *   total_tokens:int|null,
     *   estimated_cost_eur:float
     * }
     */
    public function improve(string $text, User $user, ?int $quoteId = null): array
    {
        if ($user->company_id === null || ! $user->company) {
            throw new RuntimeException('Utilizador sem empresa associada para melhoria AI.');
        }

        $company = $user->company;
        $originalText = trim($text);
        if ($originalText === '') {
            throw new RuntimeException('Texto vazio para melhoria.');
        }

        $this->usageBudgetService->ensureCanUseAi($company);

        $responseData = $this->executionService->executePrompt($this->buildPrompt($originalText));
        $improvedText = $this->sanitizeOutput($responseData->text);

        if ($improvedText === '') {
            throw new RuntimeException('Resposta AI vazia para melhoria de texto.');
        }

        $this->usageLoggerService->log(
            companyId: (int) $company->id,
            userId: (int) $user->id,
            source: 'improve_quote_text',
            responseData: $responseData,
            metadata: [
                'original_length' => mb_strlen($originalText),
                'improved_length' => mb_strlen($improvedText),
                'quote_id' => $quoteId,
            ]
        );

        return [
            'improved_text' => $improvedText,
            'model' => $responseData->model,
            'input_tokens' => $responseData->inputTokens,
            'output_tokens' => $responseData->outputTokens,
            'total_tokens' => $responseData->totalTokens,
            'estimated_cost_eur' => $responseData->estimatedCostEur,
        ];
    }

    private function buildPrompt(string $text): string
    {
        return implode("\n", [
            'Melhora o seguinte texto de orcamento profissional em portugues de Portugal.',
            'Regras:',
            '- manter significado tecnico',
            '- nao inventar materiais',
            '- nao adicionar precos',
            '- nao alterar quantidades',
            '- melhorar ortografia, clareza e tom profissional',
            '- resposta curta e limpa',
            '- sem markdown, sem aspas, sem listas',
            '- responder apenas com o texto final',
            'Texto:',
            $text,
        ]);
    }

    private function sanitizeOutput(string $text): string
    {
        $normalized = trim($text);
        if ($normalized === '') {
            return '';
        }

        $normalized = preg_replace('/```(?:\w+)?/u', '', $normalized) ?? $normalized;
        $normalized = strip_tags($normalized);
        $normalized = preg_replace('/^[\-\*\•\d\.\)\s]+/mu', '', $normalized) ?? $normalized;
        $normalized = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $normalized) ?? $normalized;
        $normalized = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;
        $normalized = trim($normalized, " \t\n\r\0\x0B\"'");

        if (mb_strlen($normalized) > 1200) {
            $normalized = mb_substr($normalized, 0, 1200);
            $normalized = rtrim($normalized, " \t\n\r\0\x0B,.;:-");
        }

        return trim($normalized);
    }
}
