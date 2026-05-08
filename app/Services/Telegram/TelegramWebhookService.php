<?php

namespace App\Services\Telegram;

use App\DTO\Telegram\TelegramAiIntentData;
use App\Exceptions\Ai\AiBudgetExceededException;
use App\Exceptions\Telegram\TelegramLinkException;
use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\TelegramEmailDraft;
use App\Models\TelegramUserLink;
use App\Services\Ai\EmailTextImproverService;
use App\Services\Telegram\Commands\TelegramConstructionSiteDailyLogAttachmentService;
use App\Services\Telegram\Commands\TelegramConstructionSiteDailyLogCommandService;
use App\Services\Telegram\Commands\TelegramCustomerBalanceCommandService;
use App\Services\Telegram\Commands\TelegramPendingQuotesCommandService;
use App\Services\Telegram\Commands\TelegramQuoteInfoCommandService;
use App\Services\Telegram\Commands\TelegramStockCommandService;
use App\Services\Telegram\Commands\TelegramSupplierBalanceCommandService;
use Illuminate\Support\Facades\Log;
use Throwable;

class TelegramWebhookService
{
    public function __construct(
        private readonly TelegramBotService $telegramBotService,
        private readonly TelegramLinkCodeService $linkCodeService,
        private readonly TelegramUserResolverService $userResolverService,
        private readonly TelegramStockCommandService $stockCommandService,
        private readonly TelegramPendingQuotesCommandService $pendingQuotesCommandService,
        private readonly TelegramQuoteInfoCommandService $quoteInfoCommandService,
        private readonly TelegramCustomerBalanceCommandService $customerBalanceCommandService,
        private readonly TelegramSupplierBalanceCommandService $supplierBalanceCommandService,
        private readonly TelegramConstructionSiteDailyLogCommandService $dailyLogCommandService,
        private readonly TelegramConstructionSiteDailyLogAttachmentService $dailyLogAttachmentService,
        private readonly TelegramPendingSelectionService $pendingSelectionService,
        private readonly TelegramAiIntentService $aiIntentService,
        private readonly TelegramEmailDraftService $telegramEmailDraftService,
        private readonly TelegramEmailAttachmentService $telegramEmailAttachmentService,
        private readonly TelegramEmailSendService $telegramEmailSendService,
        private readonly EmailTextImproverService $emailTextImproverService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function handle(array $payload): void
    {
        $message = $payload['message'] ?? null;
        if (! is_array($message)) {
            return;
        }

        $text = $this->extractIncomingText($message);
        $images = $this->extractIncomingImages($message);
        $emailAttachments = $this->extractIncomingEmailAttachments($message);

        if ($text === '' && $images === [] && $emailAttachments === []) {
            return;
        }

        $chat = $message['chat'] ?? null;
        $from = $message['from'] ?? null;

        $chatId = is_array($chat) ? ($chat['id'] ?? null) : null;
        $fromId = is_array($from) ? ($from['id'] ?? null) : null;

        if (! is_int($chatId) && ! is_string($chatId)) {
            return;
        }

        if (! is_int($fromId) && ! is_string($fromId)) {
            return;
        }

        $fromIdInt = (int) $fromId;
        $linkedUser = $this->userResolverService->resolveByTelegramUserId($fromIdInt);
        $this->touchLinkedUser($linkedUser, $chatId);

        if ($this->isLinkCommand($text)) {
            $this->handleLinkCommand($text, $chatId, $fromIdInt, $linkedUser);

            return;
        }

        if ($this->isCancelEmailCommand($text)) {
            $this->handleCancelEmailCommand($chatId, $linkedUser);

            return;
        }

        if ($this->isEmailStatusCommand($text)) {
            $this->handleEmailStatusCommand($chatId, $linkedUser);

            return;
        }

        if ($this->isEmailCommand($text)) {
            $this->handleEmailCommand($text, $chatId, $linkedUser);

            return;
        }

        if ($this->handleActiveEmailDraftConversation($text, $chatId, $linkedUser, $emailAttachments)) {
            return;
        }

        if ($this->handleStopAttachPhotosCommand($text, $chatId, $linkedUser)) {
            return;
        }

        if ($this->handlePendingSelectionReply($text, $chatId, $linkedUser)) {
            return;
        }

        if ($this->isDailyLogCommand($text)) {
            $this->handleDailyLogCommand($text, $chatId, $linkedUser, $images);

            return;
        }

        if ($this->isStockCommand($text)) {
            $this->handleStockCommand($text, $chatId, $linkedUser);

            return;
        }

        if ($this->isPendingQuotesCommand($text)) {
            $this->handlePendingQuotesCommand($chatId, $linkedUser);

            return;
        }

        if ($this->isQuoteInfoCommand($text)) {
            $this->handleQuoteInfoCommand($text, $chatId, $linkedUser);

            return;
        }

        if ($this->isCustomerBalanceCommand($text)) {
            $this->handleCustomerBalanceCommand($text, $chatId, $linkedUser);

            return;
        }

        if ($this->isSupplierBalanceCommand($text)) {
            $this->handleSupplierBalanceCommand($text, $chatId, $linkedUser);

            return;
        }

        if ($images !== [] && $linkedUser && ! $this->looksLikeDailyLogInstruction($text)) {
            if ($this->handleAttachPhotosToActiveDailyLog($chatId, $linkedUser, $images)) {
                return;
            }
        }

        if (! $this->isSlashCommand($text)) {
            $this->handleNaturalLanguageMessage($text, $chatId, $linkedUser, $images);

            return;
        }

        if (! $this->canUseBaseCommands($fromIdInt, $linkedUser)) {
            if ($this->hasWhitelistConfigured()) {
                $this->telegramBotService->sendMessage($chatId, 'Utilizador Telegram nao autorizado.');
            } else {
                $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');
            }

            return;
        }

        if ($text === '/ping') {
            $this->telegramBotService->sendMessage($chatId, 'pong');

            return;
        }

        if ($text === '/id') {
            $this->telegramBotService->sendMessage(
                $chatId,
                sprintf("Telegram user id: %d\nChat id: %s", $fromIdInt, (string) $chatId)
            );

            return;
        }

        $this->telegramBotService->sendMessage(
            $chatId,
            'Bot Telegram ligado. AI ainda nao esta ativa nesta fase.'
        );
    }

    private function isAllowedUser(int $userId): bool
    {
        /** @var array<int, int> $allowedUserIds */
        $allowedUserIds = config('telegram.allowed_user_ids', []);

        return in_array($userId, $allowedUserIds, true);
    }

    private function hasWhitelistConfigured(): bool
    {
        /** @var array<int, int> $allowedUserIds */
        $allowedUserIds = config('telegram.allowed_user_ids', []);

        return $allowedUserIds !== [];
    }

    private function canUseBaseCommands(int $telegramUserId, ?TelegramUserLink $linkedUser): bool
    {
        if ($linkedUser) {
            return true;
        }

        if (! $this->hasWhitelistConfigured()) {
            Log::info('Telegram command denied because user is not linked and whitelist is empty.', [
                'telegram_user_id' => $telegramUserId,
            ]);

            return false;
        }

        return $this->isAllowedUser($telegramUserId);
    }

    private function isLinkCommand(string $text): bool
    {
        return str_starts_with($text, '/link');
    }

    private function isStockCommand(string $text): bool
    {
        return preg_match('/^\/stock(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isPendingQuotesCommand(string $text): bool
    {
        return preg_match('/^\/orcamentos-pendentes(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isQuoteInfoCommand(string $text): bool
    {
        return preg_match('/^\/orcamento(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isCustomerBalanceCommand(string $text): bool
    {
        return preg_match('/^\/cliente-saldo(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isSupplierBalanceCommand(string $text): bool
    {
        return preg_match('/^\/fornecedor-saldo(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isDailyLogCommand(string $text): bool
    {
        return preg_match('/^\/diario(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isEmailCommand(string $text): bool
    {
        return preg_match('/^\/email(?:@[A-Za-z0-9_]+)?(?:\s+.*)?$/u', $text) === 1;
    }

    private function isCancelEmailCommand(string $text): bool
    {
        return preg_match('/^\/cancelar-email(?:@[A-Za-z0-9_]+)?$/u', $text) === 1;
    }

    private function isEmailStatusCommand(string $text): bool
    {
        return preg_match('/^\/email-status(?:@[A-Za-z0-9_]+)?$/u', $text) === 1;
    }

    private function isSlashCommand(string $text): bool
    {
        return str_starts_with($text, '/');
    }

    private function handleLinkCommand(
        string $text,
        int|string $chatId,
        int $telegramUserId,
        ?TelegramUserLink $linkedUser
    ): void {
        if ($linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Esta conta Telegram ja esta ligada ao ERP.');

            return;
        }

        $code = trim((string) preg_replace('/^\/link\s*/', '', $text));
        if ($code === '') {
            $this->telegramBotService->sendMessage($chatId, 'Envie o comando no formato: /link CODIGO');

            return;
        }

        try {
            $newLink = $this->linkCodeService->linkByCode($code, $telegramUserId, (string) $chatId);
        } catch (TelegramLinkException $exception) {
            $this->telegramBotService->sendMessage($chatId, $exception->getMessage());

            return;
        }

        $userName = trim((string) $newLink->user?->name);
        $companyName = trim((string) $newLink->company?->name);

        $this->telegramBotService->sendMessage(
            $chatId,
            sprintf(
                'Telegram ligado com sucesso ao utilizador %s / empresa %s.',
                $userName !== '' ? $userName : 'N/A',
                $companyName !== '' ? $companyName : 'N/A'
            )
        );
    }

    private function touchLinkedUser(?TelegramUserLink $linkedUser, int|string $chatId): void
    {
        if (! $linkedUser) {
            return;
        }

        $linkedUser->forceFill([
            'telegram_chat_id' => (string) $chatId,
            'last_seen_at' => now(),
        ])->save();
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     */
    private function handleDailyLogCommand(
        string $text,
        int|string $chatId,
        ?TelegramUserLink $linkedUser,
        array $images
    ): void {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $parsed = $this->parseDailyLogCommand($text);
        if ($parsed === null) {
            $this->telegramBotService->sendMessage($chatId, 'Use: /diario obra TERMO | DESCRICAO');

            return;
        }

        try {
            $result = $this->dailyLogCommandService->execute(
                link: $linkedUser,
                chatId: $chatId,
                siteTerm: $parsed['site_term'],
                description: $parsed['description'],
                images: $images
            );
        } catch (Throwable $exception) {
            Log::warning('Telegram /diario command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel criar o registo diario agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel criar o registo diario.'));
    }

    private function handleStockCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $matches = [];
        preg_match('/^\/stock(?:@[A-Za-z0-9_]+)?(?:\s+(.*))?$/u', $text, $matches);
        $term = trim((string) ($matches[1] ?? ''));

        try {
            $responseText = $this->stockCommandService->execute($linkedUser, $term);
        } catch (Throwable $exception) {
            Log::warning('Telegram /stock command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel consultar stock agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, $responseText);
    }

    private function handlePendingQuotesCommand(int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        try {
            $message = $this->pendingQuotesCommandService->execute($linkedUser);
        } catch (Throwable $exception) {
            Log::warning('Telegram /orcamentos-pendentes command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel consultar orcamentos agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, $message);
    }

    private function handleQuoteInfoCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $matches = [];
        preg_match('/^\/orcamento(?:@[A-Za-z0-9_]+)?(?:\s+(.*))?$/u', $text, $matches);
        $term = trim((string) ($matches[1] ?? ''));

        try {
            $result = $this->quoteInfoCommandService->execute($linkedUser, $chatId, $term);
        } catch (Throwable $exception) {
            Log::warning('Telegram /orcamento command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel consultar o orcamento agora. Tente novamente.');

            return;
        }

        $this->sendCommandResultWithOptionalPdf($chatId, $result);
    }

    private function handleCustomerBalanceCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $matches = [];
        preg_match('/^\/cliente-saldo(?:@[A-Za-z0-9_]+)?(?:\s+(.*))?$/u', $text, $matches);
        $term = trim((string) ($matches[1] ?? ''));

        try {
            $result = $this->customerBalanceCommandService->execute($linkedUser, $chatId, $term);
        } catch (Throwable $exception) {
            Log::warning('Telegram /cliente-saldo command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel consultar saldo do cliente agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));
    }

    private function handleSupplierBalanceCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $matches = [];
        preg_match('/^\/fornecedor-saldo(?:@[A-Za-z0-9_]+)?(?:\s+(.*))?$/u', $text, $matches);
        $term = trim((string) ($matches[1] ?? ''));

        try {
            $result = $this->supplierBalanceCommandService->execute($linkedUser, $chatId, $term);
        } catch (Throwable $exception) {
            Log::warning('Telegram /fornecedor-saldo command failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel consultar saldo do fornecedor agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));
    }

    private function handleEmailCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        if (! $this->canSendTelegramEmail($linkedUser)) {
            $this->telegramBotService->sendMessage($chatId, 'Nao tem permissao para enviar emails via Telegram.');

            return;
        }

        $matches = [];
        preg_match('/^\/email(?:@[A-Za-z0-9_]+)?(?:\s+(.*))?$/u', $text, $matches);
        $email = strtolower(trim((string) ($matches[1] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->telegramBotService->sendMessage($chatId, 'Use: /email xpto@exemplo.pt');

            return;
        }

        try {
            $existingDraft = $this->telegramEmailDraftService->getActiveDraft($linkedUser, $chatId);
            if ($existingDraft) {
                $this->telegramEmailDraftService->cancelDraft($existingDraft);
            }

            $this->telegramEmailDraftService->startDraft($linkedUser, $chatId, $email);
        } catch (Throwable $exception) {
            Log::warning('Telegram email draft start failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel iniciar o rascunho de email.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, 'Qual e o assunto?');
    }

    private function handleCancelEmailCommand(int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $cancelled = $this->telegramEmailDraftService->cancelActiveDraft($linkedUser, $chatId);
        if (! $cancelled) {
            $this->telegramBotService->sendMessage($chatId, 'Nao existe rascunho ativo.');

            return;
        }

        $this->telegramBotService->sendMessage($chatId, 'Rascunho de email cancelado.');
    }

    private function handleEmailStatusCommand(int|string $chatId, ?TelegramUserLink $linkedUser): void
    {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        $draft = $this->telegramEmailDraftService->getActiveDraft($linkedUser, $chatId);
        if (! $draft) {
            $this->telegramBotService->sendMessage($chatId, 'Nao existe rascunho de email ativo.');

            return;
        }

        $remaining = max(0, now()->diffInMinutes($draft->expires_at, false));
        $this->telegramBotService->sendMessage(
            $chatId,
            sprintf(
                "Rascunho ativo\nPara: %s\nEstado: %s\nExpira em: %d min",
                (string) $draft->to_email,
                (string) $draft->status,
                (int) $remaining
            )
        );
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     */
    private function handleActiveEmailDraftConversation(
        string $text,
        int|string $chatId,
        ?TelegramUserLink $linkedUser,
        array $incomingAttachments
    ): bool {
        if (! $linkedUser) {
            return false;
        }

        $draft = $this->telegramEmailDraftService->getActiveDraft($linkedUser, $chatId);
        if (! $draft) {
            return false;
        }

        if (! $this->canSendTelegramEmail($linkedUser)) {
            $this->telegramEmailDraftService->cancelDraft($draft);
            $this->telegramBotService->sendMessage($chatId, 'Nao tem permissao para enviar emails via Telegram.');

            return true;
        }

        $reply = $this->normalizeReply($text);

        try {
            if ($draft->status === TelegramEmailDraft::STATUS_COLLECTING_SUBJECT) {
                if ($reply === '') {
                    $this->telegramBotService->sendMessage($chatId, 'Indique o assunto do email.');

                    return true;
                }

                $this->telegramEmailDraftService->setSubject($draft, $reply);
                $this->telegramBotService->sendMessage($chatId, 'Escreva o texto do email.');

                return true;
            }

            if ($draft->status === TelegramEmailDraft::STATUS_COLLECTING_BODY) {
                if ($reply === '') {
                    $this->telegramBotService->sendMessage($chatId, 'Escreva o texto do email.');

                    return true;
                }

                $this->telegramEmailDraftService->setBody($draft, $reply);
                $this->telegramBotService->sendMessage(
                    $chatId,
                    "Quer adicionar anexos? Envie ficheiros/fotos agora ou escreva 'sem anexos' ou 'fim'."
                );

                return true;
            }

            if ($draft->status === TelegramEmailDraft::STATUS_COLLECTING_ATTACHMENTS) {
                if ($incomingAttachments !== []) {
                    $this->consumeDraftAttachments($draft, $chatId, $incomingAttachments);

                    return true;
                }

                if (in_array($this->normalizeReplyUpper($reply), ['SEM ANEXOS', 'FIM'], true)) {
                    $this->telegramEmailDraftService->moveToAiOffer($draft);
                    $this->telegramBotService->sendMessage(
                        $chatId,
                        'Quer que eu prepare uma versao mais profissional deste email? Responda SIM ou NAO.'
                    );

                    return true;
                }

                $this->telegramBotService->sendMessage(
                    $chatId,
                    "Envie anexos, ou responda 'sem anexos' / 'fim' para continuar."
                );

                return true;
            }

            if ($draft->status === TelegramEmailDraft::STATUS_AI_IMPROVEMENT_OFFER) {
                $replyUpper = $this->normalizeReplyUpper($reply);
                if ($replyUpper === 'SIM') {
                    $actorUser = $linkedUser->user;
                    if (! $actorUser) {
                        $this->telegramBotService->sendMessage($chatId, 'Utilizador associado nao encontrado.');

                        return true;
                    }

                    $improved = $this->emailTextImproverService->improve((string) ($draft->original_body ?? ''), $actorUser);
                    $this->telegramEmailDraftService->setImprovedBody($draft, (string) $improved['improved_text']);

                    $this->telegramBotService->sendMessage(
                        $chatId,
                        "Versao melhorada:\n\n".(string) $improved['improved_text']."\n\nUsar esta versao? Responda OK para usar ou ORIGINAL para manter original."
                    );

                    return true;
                }

                if ($replyUpper === 'NAO' || $replyUpper === 'NÃO') {
                    $updatedDraft = $this->telegramEmailDraftService->selectOriginalBody($draft);
                    $this->telegramBotService->sendMessage($chatId, $this->telegramEmailDraftService->buildPreview($updatedDraft));

                    return true;
                }

                $this->telegramBotService->sendMessage($chatId, 'Responda SIM para melhorar ou NAO para manter o texto original.');

                return true;
            }

            if ($draft->status === TelegramEmailDraft::STATUS_AI_IMPROVEMENT_PREVIEW) {
                $replyUpper = $this->normalizeReplyUpper($reply);
                if ($replyUpper === 'OK') {
                    $updatedDraft = $this->telegramEmailDraftService->selectImprovedBody($draft);
                    $this->telegramBotService->sendMessage($chatId, $this->telegramEmailDraftService->buildPreview($updatedDraft));

                    return true;
                }

                if ($replyUpper === 'ORIGINAL') {
                    $updatedDraft = $this->telegramEmailDraftService->selectOriginalBody($draft);
                    $this->telegramBotService->sendMessage($chatId, $this->telegramEmailDraftService->buildPreview($updatedDraft));

                    return true;
                }

                $this->telegramBotService->sendMessage($chatId, 'Responda OK para usar a versao melhorada ou ORIGINAL para manter o texto original.');

                return true;
            }

            if ($draft->status === TelegramEmailDraft::STATUS_AWAITING_FINAL_APPROVAL) {
                $replyUpper = $this->normalizeReplyUpper($reply);
                if ($replyUpper === 'CANCELAR') {
                    $this->telegramEmailDraftService->cancelDraft($draft);
                    $this->telegramBotService->sendMessage($chatId, 'Rascunho cancelado.');

                    return true;
                }

                if ($replyUpper === 'OK ENVIAR') {
                    $sendResult = $this->telegramEmailSendService->send($draft);

                    if (! $sendResult['success']) {
                        $this->telegramBotService->sendMessage($chatId, (string) $sendResult['message']);

                        return true;
                    }

                    $this->telegramEmailDraftService->markSent($draft);
                    $this->telegramBotService->sendMessage($chatId, 'Email enviado com sucesso.');

                    return true;
                }

                $this->telegramBotService->sendMessage($chatId, 'Responda OK ENVIAR para enviar ou CANCELAR.');

                return true;
            }
        } catch (AiBudgetExceededException) {
            $this->telegramBotService->sendMessage($chatId, 'Limite mensal de AI atingido para esta empresa.');

            return true;
        } catch (Throwable $exception) {
            Log::warning('Telegram email draft flow failed', [
                'draft_id' => (int) $draft->id,
                'company_id' => (int) $draft->company_id,
                'telegram_user_id' => (int) $draft->telegram_user_id,
                'status' => (string) $draft->status,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel processar o rascunho de email agora.');

            return true;
        }

        return false;
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,file_name:string|null,mime_type:string|null,source:string}> $incomingAttachments
     */
    private function consumeDraftAttachments(TelegramEmailDraft $draft, int|string $chatId, array $incomingAttachments): void
    {
        $currentAttachments = $this->telegramEmailDraftService->attachments($draft);
        $maxAttachments = max(1, (int) config('telegram.email.max_attachments', TelegramEmailDraftService::MAX_ATTACHMENTS));

        if (count($currentAttachments) >= $maxAttachments) {
            $this->telegramBotService->sendMessage($chatId, "Ja atingiu o limite de {$maxAttachments} anexos. Escreva fim para continuar.");

            return;
        }

        $accepted = 0;
        $rejected = 0;
        $lastReason = null;

        foreach ($incomingAttachments as $attachmentPayload) {
            $currentAttachments = $this->telegramEmailDraftService->attachments($draft);
            if (count($currentAttachments) >= $maxAttachments) {
                break;
            }

            $result = $this->telegramEmailAttachmentService->handleIncomingAttachment($draft, $attachmentPayload);
            if (! $result['accepted']) {
                $rejected++;
                $lastReason = (string) ($result['reason'] ?? 'Anexo invalido.');
                continue;
            }

            $attachment = $result['attachment'] ?? null;
            if (! is_array($attachment)) {
                $rejected++;
                continue;
            }

            $draft = $this->telegramEmailDraftService->addAttachment($draft, $attachment);
            $accepted++;
        }

        if ($accepted > 0 && $rejected === 0) {
            $this->telegramBotService->sendMessage($chatId, 'Anexo recebido. Envie mais anexos ou escreva fim.');

            return;
        }

        if ($accepted > 0 && $rejected > 0) {
            $this->telegramBotService->sendMessage(
                $chatId,
                sprintf('%d anexo(s) guardado(s). %d rejeitado(s). Envie mais anexos ou escreva fim.', $accepted, $rejected)
            );

            return;
        }

        $this->telegramBotService->sendMessage($chatId, $lastReason ?: 'Anexo invalido.');
    }

    private function canSendTelegramEmail(TelegramUserLink $linkedUser): bool
    {
        $linkedUser->loadMissing('user');

        return $linkedUser->user?->can('company.telegram.email.send') === true;
    }

    private function normalizeReply(string $text): string
    {
        $value = trim($text);
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', $value) ?? $value;

        return trim($value);
    }

    private function normalizeReplyUpper(string $text): string
    {
        return mb_strtoupper($this->normalizeReply($text), 'UTF-8');
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     */
    private function handleNaturalLanguageMessage(
        string $text,
        int|string $chatId,
        ?TelegramUserLink $linkedUser,
        array $images
    ): void {
        if (! $linkedUser) {
            $this->telegramBotService->sendMessage($chatId, 'Conta Telegram nao ligada. Use /link CODIGO.');

            return;
        }

        try {
            $intentData = $this->aiIntentService->detect($text, $linkedUser);
        } catch (AiBudgetExceededException) {
            $this->telegramBotService->sendMessage($chatId, 'Limite mensal de AI atingido para esta empresa.');

            return;
        } catch (Throwable $exception) {
            Log::warning('Telegram AI intent failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage(
                $chatId,
                'Nao consegui interpretar o pedido agora. Tente novamente.'
            );

            return;
        }

        $this->dispatchNaturalIntent($intentData, $linkedUser, $chatId, $images);
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     */
    private function dispatchNaturalIntent(
        TelegramAiIntentData $intentData,
        TelegramUserLink $linkedUser,
        int|string $chatId,
        array $images
    ): void {
        try {
            if ($intentData->intent === TelegramAiIntentData::INTENT_STOCK_LOOKUP && $intentData->term !== null) {
                $message = $this->stockCommandService->execute($linkedUser, $intentData->term);
                $this->telegramBotService->sendMessage($chatId, $message);

                return;
            }

            if ($intentData->intent === TelegramAiIntentData::INTENT_PENDING_QUOTES_LOOKUP) {
                $message = $this->pendingQuotesCommandService->execute($linkedUser);
                $this->telegramBotService->sendMessage($chatId, $message);

                return;
            }

            if ($intentData->intent === TelegramAiIntentData::INTENT_QUOTE_INFO_LOOKUP && $intentData->term !== null) {
                $result = $this->quoteInfoCommandService->execute($linkedUser, $chatId, $intentData->term);
                $this->sendCommandResultWithOptionalPdf($chatId, $result);

                return;
            }

            if ($intentData->intent === TelegramAiIntentData::INTENT_CUSTOMER_BALANCE_LOOKUP && $intentData->term !== null) {
                $result = $this->customerBalanceCommandService->execute($linkedUser, $chatId, $intentData->term);
                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));

                return;
            }

            if ($intentData->intent === TelegramAiIntentData::INTENT_SUPPLIER_BALANCE_LOOKUP && $intentData->term !== null) {
                $result = $this->supplierBalanceCommandService->execute($linkedUser, $chatId, $intentData->term);
                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));

                return;
            }

            if (
                $intentData->intent === TelegramAiIntentData::INTENT_CREATE_CONSTRUCTION_SITE_DAILY_LOG
                && $intentData->siteTerm !== null
                && $intentData->description !== null
            ) {
                $result = $this->dailyLogCommandService->execute(
                    link: $linkedUser,
                    chatId: $chatId,
                    siteTerm: $intentData->siteTerm,
                    description: $intentData->description,
                    images: $images
                );

                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel criar o registo diario.'));

                return;
            }

            if ($intentData->intent === TelegramAiIntentData::INTENT_SEND_EMAIL_START && $intentData->term !== null) {
                if (! filter_var($intentData->term, FILTER_VALIDATE_EMAIL)) {
                    $this->telegramBotService->sendMessage($chatId, 'Use: /email xpto@exemplo.pt');

                    return;
                }

                $this->handleEmailCommand('/email '.$intentData->term, $chatId, $linkedUser);

                return;
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram natural intent dispatch failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'intent' => $intentData->intent,
                'error' => $exception->getMessage(),
            ]);

            $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel processar o pedido agora. Tente novamente.');

            return;
        }

        $this->telegramBotService->sendMessage(
            $chatId,
            'Posso ajudar com stock, orcamentos pendentes, informacao de orcamentos, saldos, registos diarios de obra e envio de email.'
        );
    }

    private function handlePendingSelectionReply(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): bool
    {
        if (! $linkedUser) {
            return false;
        }

        $selectionResult = $this->pendingSelectionService->consumeNumericReply($linkedUser, $chatId, $text);
        if ($selectionResult === null) {
            return false;
        }

        $status = (string) ($selectionResult['status'] ?? '');
        if ($status === 'expired' || $status === 'invalid') {
            $this->telegramBotService->sendMessage($chatId, (string) ($selectionResult['message'] ?? 'Selecao invalida.'));

            return true;
        }

        if ($status !== 'resolved') {
            return true;
        }

        $type = (string) ($selectionResult['type'] ?? '');
        $selectedId = (int) ($selectionResult['selected_id'] ?? 0);
        $payload = is_array($selectionResult['payload'] ?? null) ? $selectionResult['payload'] : [];

        if ($selectedId <= 0) {
            $this->telegramBotService->sendMessage($chatId, 'Selecao invalida. Faca o pedido novamente.');

            return true;
        }

        try {
            if ($type === TelegramPendingSelectionService::TYPE_QUOTE_INFO) {
                $result = $this->quoteInfoCommandService->executeByQuoteId($linkedUser, $selectedId);
                $this->sendCommandResultWithOptionalPdf($chatId, $result);

                return true;
            }

            if ($type === TelegramPendingSelectionService::TYPE_CUSTOMER_BALANCE) {
                $result = $this->customerBalanceCommandService->executeByCustomerId($linkedUser, $selectedId);
                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));

                return true;
            }

            if ($type === TelegramPendingSelectionService::TYPE_SUPPLIER_BALANCE) {
                $result = $this->supplierBalanceCommandService->executeBySupplierId($linkedUser, $selectedId);
                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel obter o saldo.'));

                return true;
            }

            if ($type === TelegramPendingSelectionService::TYPE_CONSTRUCTION_SITE_DAILY_LOG_CREATE) {
                $description = trim((string) ($payload['description'] ?? ''));
                $images = $this->normalizeImagePayloadList($payload['images'] ?? []);

                $result = $this->dailyLogCommandService->executeBySiteId(
                    link: $linkedUser,
                    chatId: $chatId,
                    siteId: $selectedId,
                    description: $description,
                    images: $images
                );

                $this->telegramBotService->sendMessage($chatId, (string) ($result['message'] ?? 'Nao foi possivel criar o registo diario.'));

                return true;
            }
        } catch (Throwable $exception) {
            Log::warning('Telegram pending selection execution failed', [
                'telegram_user_id' => $linkedUser->telegram_user_id,
                'company_id' => $linkedUser->company_id,
                'selection_type' => $type,
                'selected_id' => $selectedId,
                'error' => $exception->getMessage(),
            ]);
        }

        $this->telegramBotService->sendMessage($chatId, 'Nao foi possivel processar a selecao. Faca o pedido novamente.');

        return true;
    }

    private function handleStopAttachPhotosCommand(string $text, int|string $chatId, ?TelegramUserLink $linkedUser): bool
    {
        if (! $linkedUser) {
            return false;
        }

        if (! in_array(mb_strtolower(trim($text), 'UTF-8'), ['fim', '/fim'], true)) {
            return false;
        }

        $selection = $this->pendingSelectionService->getActiveSelectionByType(
            $linkedUser,
            $chatId,
            TelegramPendingSelectionService::TYPE_DAILY_LOG_ATTACH_PHOTOS
        );

        if (! $selection) {
            return false;
        }

        $this->pendingSelectionService->consumeSelection($selection);
        $this->telegramBotService->sendMessage($chatId, 'Modo de anexar fotos terminado.');

        return true;
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     */
    private function handleAttachPhotosToActiveDailyLog(int|string $chatId, TelegramUserLink $linkedUser, array $images): bool
    {
        if ($images === []) {
            return false;
        }

        $selection = $this->pendingSelectionService->getActiveSelectionByType(
            $linkedUser,
            $chatId,
            TelegramPendingSelectionService::TYPE_DAILY_LOG_ATTACH_PHOTOS
        );

        if (! $selection) {
            return false;
        }

        $payload = is_array($selection->payload) ? $selection->payload : [];
        $logId = (int) ($payload['log_id'] ?? 0);
        $siteId = (int) ($payload['construction_site_id'] ?? 0);

        if ($logId <= 0 || $siteId <= 0) {
            $this->pendingSelectionService->consumeSelection($selection);
            $this->telegramBotService->sendMessage($chatId, 'Contexto de anexos invalido. Crie um novo registo diario.');

            return true;
        }

        $companyId = (int) $linkedUser->company_id;
        $site = ConstructionSite::query()
            ->forCompany($companyId)
            ->whereKey($siteId)
            ->first();

        $log = ConstructionSiteLog::query()
            ->forCompany($companyId)
            ->where('construction_site_id', $siteId)
            ->whereKey($logId)
            ->first();

        if (! $site || ! $log) {
            $this->pendingSelectionService->consumeSelection($selection);
            $this->telegramBotService->sendMessage($chatId, 'Registo diario nao encontrado. Crie um novo registo.');

            return true;
        }

        $result = $this->dailyLogAttachmentService->attachImages($site, $log, $images);

        if ($result['attached'] > 0 && $result['rejected'] === 0) {
            $this->telegramBotService->sendMessage($chatId, '📎 Foto anexada ao registo diario.');

            return true;
        }

        if ($result['attached'] > 0 && $result['rejected'] > 0) {
            $this->telegramBotService->sendMessage($chatId, sprintf(
                '📎 %d foto(s) anexada(s). %d rejeitada(s) por formato/tamanho.',
                (int) $result['attached'],
                (int) $result['rejected']
            ));

            return true;
        }

        $this->telegramBotService->sendMessage($chatId, 'Ficheiro invalido ou acima do tamanho permitido.');

        return true;
    }

    /**
     * @param array{message?:string,pdf_path?:?string,pdf_caption?:?string} $result
     */
    private function sendCommandResultWithOptionalPdf(int|string $chatId, array $result): void
    {
        $message = trim((string) ($result['message'] ?? ''));
        if ($message !== '') {
            $this->telegramBotService->sendMessage($chatId, $message);
        }

        $pdfPath = trim((string) ($result['pdf_path'] ?? ''));
        if ($pdfPath !== '') {
            $this->telegramBotService->sendDocument(
                $chatId,
                $pdfPath,
                isset($result['pdf_caption']) ? (string) $result['pdf_caption'] : null
            );
        }
    }

    /**
     * @param array<string,mixed> $message
     */
    private function extractIncomingText(array $message): string
    {
        $text = trim((string) ($message['text'] ?? ''));
        if ($text !== '') {
            return $text;
        }

        return trim((string) ($message['caption'] ?? ''));
    }

    /**
     * @param array<string,mixed> $message
     * @return list<array{file_id:string,file_size:int|null,source:string}>
     */
    private function extractIncomingImages(array $message): array
    {
        $images = [];

        $photoSizes = $message['photo'] ?? null;
        if (is_array($photoSizes) && $photoSizes !== []) {
            $largest = $this->pickLargestPhotoSize($photoSizes);
            if ($largest !== null) {
                $images[] = [
                    'file_id' => (string) $largest['file_id'],
                    'file_size' => isset($largest['file_size']) && is_numeric($largest['file_size'])
                        ? (int) $largest['file_size']
                        : null,
                    'source' => 'photo',
                ];
            }
        }

        $document = $message['document'] ?? null;
        if (is_array($document)) {
            $fileId = trim((string) ($document['file_id'] ?? ''));
            $mimeType = trim((string) ($document['mime_type'] ?? ''));

            if ($fileId !== '' && str_starts_with($mimeType, 'image/')) {
                $images[] = [
                    'file_id' => $fileId,
                    'file_size' => isset($document['file_size']) && is_numeric($document['file_size'])
                        ? (int) $document['file_size']
                        : null,
                    'source' => 'document',
                ];
            }
        }

        if ($images === []) {
            return [];
        }

        $deduped = [];
        $seen = [];

        foreach ($images as $image) {
            if (isset($seen[$image['file_id']])) {
                continue;
            }

            $seen[$image['file_id']] = true;
            $deduped[] = $image;
        }

        return $deduped;
    }

    /**
     * @param array<string,mixed> $message
     * @return list<array{file_id:string,file_size:int|null,file_name:string|null,mime_type:string|null,source:string}>
     */
    private function extractIncomingEmailAttachments(array $message): array
    {
        $attachments = [];

        $photoSizes = $message['photo'] ?? null;
        if (is_array($photoSizes) && $photoSizes !== []) {
            $largest = $this->pickLargestPhotoSize($photoSizes);
            if ($largest !== null) {
                $attachments[] = [
                    'file_id' => (string) $largest['file_id'],
                    'file_size' => isset($largest['file_size']) && is_numeric($largest['file_size'])
                        ? (int) $largest['file_size']
                        : null,
                    'file_name' => null,
                    'mime_type' => 'image/jpeg',
                    'source' => 'photo',
                ];
            }
        }

        $document = $message['document'] ?? null;
        if (is_array($document)) {
            $fileId = trim((string) ($document['file_id'] ?? ''));
            if ($fileId !== '') {
                $attachments[] = [
                    'file_id' => $fileId,
                    'file_size' => isset($document['file_size']) && is_numeric($document['file_size'])
                        ? (int) $document['file_size']
                        : null,
                    'file_name' => isset($document['file_name']) ? trim((string) $document['file_name']) : null,
                    'mime_type' => isset($document['mime_type']) ? trim((string) $document['mime_type']) : null,
                    'source' => 'document',
                ];
            }
        }

        if ($attachments === []) {
            return [];
        }

        $unique = [];
        $seen = [];
        foreach ($attachments as $attachment) {
            if (isset($seen[$attachment['file_id']])) {
                continue;
            }

            $seen[$attachment['file_id']] = true;
            $unique[] = $attachment;
        }

        return $unique;
    }

    /**
     * @param array<int,mixed> $photoSizes
     * @return array<string,mixed>|null
     */
    private function pickLargestPhotoSize(array $photoSizes): ?array
    {
        $best = null;
        $bestSize = -1;

        foreach ($photoSizes as $size) {
            if (! is_array($size)) {
                continue;
            }

            $fileId = trim((string) ($size['file_id'] ?? ''));
            if ($fileId === '') {
                continue;
            }

            $currentSize = isset($size['file_size']) && is_numeric($size['file_size'])
                ? (int) $size['file_size']
                : 0;

            if ($currentSize >= $bestSize) {
                $bestSize = $currentSize;
                $best = $size;
            }
        }

        return $best;
    }

    /**
     * @return array{site_term:string,description:string}|null
     */
    private function parseDailyLogCommand(string $text): ?array
    {
        $matches = [];

        if (preg_match('/^\/diario(?:@[A-Za-z0-9_]+)?\s+obra\s+(.+?)\s*\|\s*(.+)$/iu', $text, $matches) !== 1) {
            return null;
        }

        $siteTerm = trim((string) ($matches[1] ?? ''));
        $description = trim((string) ($matches[2] ?? ''));

        if ($siteTerm === '' || $description === '') {
            return null;
        }

        return [
            'site_term' => $siteTerm,
            'description' => $description,
        ];
    }

    private function looksLikeDailyLogInstruction(string $text): bool
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'obra')
            && (
                str_contains($normalized, 'diario')
                || str_contains($normalized, 'registo')
                || str_contains($normalized, 'registar')
                || str_contains($normalized, 'adicionar')
                || str_contains($normalized, 'criar')
            );
    }

    /**
     * @param mixed $raw
     * @return list<array{file_id:string,file_size:int|null,source:string}>
     */
    private function normalizeImagePayloadList(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $normalized = [];

        foreach ($raw as $item) {
            if (! is_array($item)) {
                continue;
            }

            $fileId = trim((string) ($item['file_id'] ?? ''));
            if ($fileId === '') {
                continue;
            }

            $normalized[] = [
                'file_id' => $fileId,
                'file_size' => isset($item['file_size']) && is_numeric($item['file_size'])
                    ? (int) $item['file_size']
                    : null,
                'source' => is_string($item['source'] ?? null) ? trim((string) $item['source']) : 'photo',
            ];
        }

        return $normalized;
    }
}
