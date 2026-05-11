<?php

namespace App\Http\Controllers\Ai;

use App\Exceptions\Ai\AiBudgetExceededException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\ImproveQuoteTextRequest;
use App\Models\User;
use App\Services\Ai\QuoteTextImproverService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class ImproveQuoteTextController extends Controller
{
    public function __construct(
        private readonly QuoteTextImproverService $quoteTextImproverService
    ) {
    }

    public function __invoke(ImproveQuoteTextRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $this->quoteTextImproverService->improve(
                text: (string) $request->validated('text'),
                user: $user,
                quoteId: $request->validated('quote_id') !== null ? (int) $request->validated('quote_id') : null
            );
        } catch (AiBudgetExceededException) {
            return response()->json([
                'message' => 'Limite mensal de AI atingido. Aumente o orcamento ou aguarde pelo proximo mes.',
            ], 422);
        } catch (Throwable $exception) {
            Log::warning('Quote text AI improvement failed.', [
                'user_id' => (int) $user->id,
                'company_id' => (int) $user->company_id,
                'quote_id' => $request->validated('quote_id'),
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Nao foi possivel melhorar o texto agora.',
            ], 422);
        }

        return response()->json($result);
    }
}
