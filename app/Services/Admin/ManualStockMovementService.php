<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualStockMovementService
{
    public function create(
        int $companyId,
        int $performedByUserId,
        array $payload
    ): StockMovement {
        return DB::transaction(function () use ($companyId, $performedByUserId, $payload): StockMovement {
            /** @var Article|null $article */
            $article = Article::query()
                ->forCompany($companyId)
                ->whereKey((int) $payload['article_id'])
                ->lockForUpdate()
                ->first();

            if (! $article) {
                throw ValidationException::withMessages([
                    'article_id' => 'O artigo selecionado nao pertence a empresa atual.',
                ]);
            }

            if (! $article->canMoveStock()) {
                throw ValidationException::withMessages([
                    'article_id' => 'O artigo selecionado nao movimenta stock.',
                ]);
            }

            $type = (string) $payload['type'];
            $direction = StockMovement::directionForType($type);
            if ($direction === null) {
                throw ValidationException::withMessages([
                    'type' => 'O tipo de movimento selecionado nao e valido.',
                ]);
            }

            $reasonCode = (string) $payload['reason_code'];
            if (! in_array($reasonCode, StockMovement::reasonCodesForType($type), true)) {
                throw ValidationException::withMessages([
                    'reason_code' => 'O motivo selecionado nao e valido para o tipo de movimento.',
                ]);
            }

            $quantity = round((float) $payload['quantity'], 3);
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'A quantidade tem de ser maior que zero.',
                ]);
            }

            if ($direction === StockMovement::DIRECTION_OUT && ! $article->hasSufficientStockFor($quantity)) {
                throw ValidationException::withMessages([
                    'quantity' => 'Stock insuficiente para esta saida.',
                ]);
            }

            $movementDate = isset($payload['movement_date']) && $payload['movement_date'] !== null
                ? (string) $payload['movement_date']
                : now()->toDateString();

            $movement = StockMovement::query()->create([
                'company_id' => $companyId,
                'article_id' => (int) $article->id,
                'type' => $type,
                'direction' => $direction,
                'reason_code' => $reasonCode,
                'quantity' => $quantity,
                'unit_cost' => null,
                'reference_type' => StockMovement::REFERENCE_MANUAL,
                'reference_id' => 0,
                'reference_line_id' => null,
                'movement_date' => $movementDate,
                'notes' => $payload['notes'] ?? null,
                'performed_by' => $performedByUserId,
            ]);

            if ($direction === StockMovement::DIRECTION_IN) {
                $article->increaseStock($quantity);
            } else {
                $article->decreaseStock($quantity);
            }

            return $movement->fresh(['article:id,code,designation', 'performer:id,name']);
        });
    }
}
