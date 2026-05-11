<?php

namespace App\Services\Ai;

use App\DTO\Ai\AiResponseData;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Throwable;

class AiExecutionService
{
    public function executePrompt(string $prompt): AiResponseData
    {
        if (! config('ai.enabled')) {
            throw new RuntimeException('Assistente AI encontra-se desativado nas configuracoes.');
        }

        $model = (string) config('ai.model', 'gpt-5.4-nano');
        $timeout = max(5, (int) config('ai.timeout', 20));
        $maxOutputTokens = max(64, (int) config('ai.max_output_tokens', 1200));

        config(['openai.request_timeout' => $timeout]);

        try {
            $response = OpenAI::responses()->create([
                'model' => $model,
                'input' => $prompt,
                'max_output_tokens' => $maxOutputTokens,
            ]);
        } catch (Throwable $exception) {
            throw new RuntimeException('Falha ao comunicar com a API OpenAI.', 0, $exception);
        }

        $payload = $response->toArray();
        $text = $this->extractTextFromOutputPayload($payload);

        $usage = is_array($payload['usage'] ?? null) ? $payload['usage'] : [];
        $inputTokens = isset($usage['input_tokens']) ? (int) $usage['input_tokens'] : null;
        $outputTokens = isset($usage['output_tokens']) ? (int) $usage['output_tokens'] : null;
        $totalTokens = isset($usage['total_tokens']) ? (int) $usage['total_tokens'] : null;

        return new AiResponseData(
            text: $text !== '' ? $text : '(Sem resposta textual)',
            model: (string) ($payload['model'] ?? $model),
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            totalTokens: $totalTokens,
            estimatedCostEur: $this->estimateCost($inputTokens, $outputTokens),
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function extractTextFromOutputPayload(array $payload): string
    {
        $directText = trim((string) ($payload['output_text'] ?? ''));
        if ($directText !== '') {
            return $directText;
        }

        $output = $payload['output'] ?? null;
        if (! is_array($output)) {
            return '';
        }

        $chunks = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            $contents = $item['content'] ?? null;
            if (! is_array($contents)) {
                continue;
            }

            foreach ($contents as $content) {
                if (! is_array($content)) {
                    continue;
                }

                $value = trim((string) ($content['text'] ?? ''));
                if ($value !== '') {
                    $chunks[] = $value;
                }
            }
        }

        return trim(implode("\n\n", $chunks));
    }

    private function estimateCost(?int $inputTokens, ?int $outputTokens): float
    {
        $inputPricePerMillion = max(0.0, (float) config('ai.pricing.input_eur_per_1m', 0.0));
        $outputPricePerMillion = max(0.0, (float) config('ai.pricing.output_eur_per_1m', 0.0));

        $inputCost = (($inputTokens ?? 0) / 1_000_000) * $inputPricePerMillion;
        $outputCost = (($outputTokens ?? 0) / 1_000_000) * $outputPricePerMillion;

        return round($inputCost + $outputCost, 6);
    }
}
