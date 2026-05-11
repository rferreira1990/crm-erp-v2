<?php

namespace App\DTO\Ai;

final readonly class AiResponseData
{
    public function __construct(
        public string $text,
        public string $model,
        public ?int $inputTokens,
        public ?int $outputTokens,
        public ?int $totalTokens,
        public float $estimatedCostEur,
    ) {
    }

    /**
     * @return array{
     *   text:string,
     *   model:string,
     *   input_tokens:int|null,
     *   output_tokens:int|null,
     *   total_tokens:int|null,
     *   estimated_cost_eur:float
     * }
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'model' => $this->model,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
            'estimated_cost_eur' => $this->estimatedCostEur,
        ];
    }
}

