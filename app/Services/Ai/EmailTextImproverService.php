<?php

namespace App\Services\Ai;

use App\Models\User;
use RuntimeException;

class EmailTextImproverService
{
    public function __construct(
        private readonly AiExecutionService $executionService,
        private readonly AiUsageBudgetService $usageBudgetService,
        private readonly AiUsageLoggerService $usageLoggerService
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
    public function improve(string $text, User $user): array
    {
        if ($user->company_id === null || ! $user->company) {
            throw new RuntimeException('Utilizador sem empresa associada.');
        }

        $originalText = trim($text);
        if ($originalText === '') {
            throw new RuntimeException('Texto vazio para melhoria.');
        }

        $this->usageBudgetService->ensureCanUseAi($user->company);

        $responseData = $this->executionService->executePrompt($this->buildPrompt($originalText));
        $improved = $this->sanitizeOutput($responseData->text);
        if ($improved === '') {
            throw new RuntimeException('Nao foi possivel melhorar o texto.');
        }

        $this->usageLoggerService->log(
            companyId: (int) $user->company_id,
            userId: (int) $user->id,
            source: 'telegram_email_improve',
            responseData: $responseData,
            metadata: [
                'original_length' => mb_strlen($originalText),
                'improved_length' => mb_strlen($improved),
            ]
        );

        return [
            'improved_text' => $improved,
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
            'Melhora o texto de email em portugues de Portugal.',
            'Regras:',
            '- manter a intencao original',
            '- nao inventar factos',
            '- nao prometer prazos nao mencionados',
            '- nao alterar valores',
            '- tom cordial e profissional',
            '- responder apenas com o corpo do email final',
            'Texto original:',
            $text,
        ]);
    }

    private function sanitizeOutput(string $text): string
    {
        $value = trim($text);
        if ($value === '') {
            return '';
        }

        $value = preg_replace('/```(?:\w+)?/u', '', $value) ?? $value;
        $value = strip_tags($value);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace("/\r\n|\r/u", "\n", $value) ?? $value;
        $value = preg_replace("/\n{3,}/u", "\n\n", $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (mb_strlen($value) > 5000) {
            $value = mb_substr($value, 0, 5000);
            $value = rtrim($value);
        }

        return trim($value);
    }
}

