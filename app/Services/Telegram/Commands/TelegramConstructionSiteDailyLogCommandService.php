<?php

namespace App\Services\Telegram\Commands;

use App\Models\ConstructionSite;
use App\Models\ConstructionSiteLog;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Telegram\TelegramPendingSelectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TelegramConstructionSiteDailyLogCommandService
{
    public function __construct(
        private readonly TelegramPendingSelectionService $pendingSelectionService,
        private readonly TelegramConstructionSiteDailyLogAttachmentService $attachmentService
    ) {
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     * @return array{
     *   status:string,
     *   message:string,
     *   created:bool,
     *   log_id:int|null,
     *   site_id:int|null
     * }
     */
    public function execute(
        TelegramUserLink $link,
        int|string $chatId,
        string $siteTerm,
        string $description,
        array $images = []
    ): array {
        $normalizedTerm = trim($siteTerm);
        $normalizedDescription = trim($description);

        if ($normalizedTerm === '' || $normalizedDescription === '') {
            return [
                'status' => 'invalid',
                'message' => 'Use: /diario obra TERMO | DESCRICAO',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        if (mb_strlen($normalizedDescription) < 10) {
            return [
                'status' => 'invalid',
                'message' => 'A descricao deve ter pelo menos 10 caracteres.',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        $user = $this->resolveActorUser($link);
        if (! $user || ! $user->can('company.construction_site_logs.create')) {
            return [
                'status' => 'forbidden',
                'message' => 'Nao tem permissao para criar registos de obra.',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        $companyId = (int) $link->company_id;
        $sites = $this->searchConstructionSites($companyId, $normalizedTerm);

        if ($sites->isEmpty()) {
            return [
                'status' => 'not_found',
                'message' => sprintf('Nao encontrei nenhuma obra para: %s', $normalizedTerm),
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        if ($sites->count() > 1) {
            $this->pendingSelectionService->createSelection(
                link: $link,
                chatId: $chatId,
                type: TelegramPendingSelectionService::TYPE_CONSTRUCTION_SITE_DAILY_LOG_CREATE,
                payload: [
                    'ids' => $sites->pluck('id')->take(5)->values()->all(),
                    'description' => $normalizedDescription,
                    'images' => $images,
                ]
            );

            return [
                'status' => 'multiple',
                'message' => $this->multipleSitesMessage($sites),
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        $site = $sites->firstOrFail();

        return $this->createForResolvedSite($link, $chatId, $site, $normalizedDescription, $images);
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     * @return array{
     *   status:string,
     *   message:string,
     *   created:bool,
     *   log_id:int|null,
     *   site_id:int|null
     * }
     */
    public function executeBySiteId(
        TelegramUserLink $link,
        int|string $chatId,
        int $siteId,
        string $description,
        array $images = []
    ): array {
        $normalizedDescription = trim($description);
        if ($siteId <= 0 || $normalizedDescription === '') {
            return [
                'status' => 'invalid',
                'message' => 'Selecao invalida. Faca o pedido novamente.',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        $user = $this->resolveActorUser($link);
        if (! $user || ! $user->can('company.construction_site_logs.create')) {
            return [
                'status' => 'forbidden',
                'message' => 'Nao tem permissao para criar registos de obra.',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        $site = ConstructionSite::query()
            ->forCompany((int) $link->company_id)
            ->with(['customer:id,name'])
            ->whereKey($siteId)
            ->first();

        if (! $site) {
            return [
                'status' => 'not_found',
                'message' => 'Obra nao encontrada para a selecao indicada.',
                'created' => false,
                'log_id' => null,
                'site_id' => null,
            ];
        }

        return $this->createForResolvedSite($link, $chatId, $site, $normalizedDescription, $images);
    }

    /**
     * @param list<array{file_id:string,file_size:int|null,source:string}> $images
     * @return array{
     *   status:string,
     *   message:string,
     *   created:bool,
     *   log_id:int|null,
     *   site_id:int|null
     * }
     */
    private function createForResolvedSite(
        TelegramUserLink $link,
        int|string $chatId,
        ConstructionSite $site,
        string $description,
        array $images
    ): array {
        $companyId = (int) $link->company_id;
        $userId = (int) $link->user_id;

        /** @var ConstructionSiteLog $log */
        $log = DB::transaction(function () use ($companyId, $site, $userId, $description): ConstructionSiteLog {
            return ConstructionSiteLog::query()->create([
                'company_id' => $companyId,
                'construction_site_id' => (int) $site->id,
                'log_date' => now()->toDateString(),
                'type' => ConstructionSiteLog::TYPE_PROGRESS,
                'title' => 'Registo via Telegram',
                'description' => $description,
                'is_important' => false,
                'created_by' => $userId,
                'assigned_user_id' => null,
            ]);
        });

        $attachResult = $this->attachmentService->attachImages($site, $log, $images);

        $this->pendingSelectionService->createSelection(
            link: $link,
            chatId: $chatId,
            type: TelegramPendingSelectionService::TYPE_DAILY_LOG_ATTACH_PHOTOS,
            payload: [
                'log_id' => (int) $log->id,
                'construction_site_id' => (int) $site->id,
            ]
        );

        $messageLines = [
            sprintf(
                '✅ Registo diario criado na obra %s — %s.',
                (string) $site->code,
                trim((string) $site->name) !== '' ? (string) $site->name : 'Sem nome'
            ),
            'Descricao: '.$description,
        ];

        if ($attachResult['attached'] > 0) {
            $messageLines[] = sprintf('📎 %d foto(s) anexada(s) ao registo diario.', (int) $attachResult['attached']);
        }

        if ($attachResult['rejected'] > 0) {
            $messageLines[] = sprintf('⚠️ %d ficheiro(s) foram rejeitados por formato/tamanho.', (int) $attachResult['rejected']);
        }

        $messageLines[] = 'Pode enviar fotos nos proximos 10 minutos para anexar.';
        $messageLines[] = 'Envie "fim" para terminar o modo de anexar.';

        return [
            'status' => 'created',
            'message' => implode("\n", $messageLines),
            'created' => true,
            'log_id' => (int) $log->id,
            'site_id' => (int) $site->id,
        ];
    }

    /**
     * @return Collection<int, ConstructionSite>
     */
    private function searchConstructionSites(int $companyId, string $term): Collection
    {
        $termLower = mb_strtolower($term, 'UTF-8');

        return ConstructionSite::query()
            ->forCompany($companyId)
            ->with(['customer:id,name'])
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';

                $query->where('code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('locality', 'like', $like)
                    ->orWhere('city', 'like', $like)
                    ->orWhereHas('customer', function (Builder $customerQuery) use ($like): void {
                        $customerQuery->where('name', 'like', $like);
                    });
            })
            ->orderByRaw(
                'CASE
                    WHEN LOWER(code) = ? THEN 0
                    WHEN LOWER(name) = ? THEN 1
                    WHEN LOWER(code) LIKE ? THEN 2
                    WHEN LOWER(name) LIKE ? THEN 3
                    ELSE 4
                END',
                [$termLower, $termLower, $termLower.'%', $termLower.'%']
            )
            ->orderBy('name')
            ->limit(5)
            ->get();
    }

    /**
     * @param Collection<int, ConstructionSite> $sites
     */
    private function multipleSitesMessage(Collection $sites): string
    {
        $lines = ['Encontrei varias obras:', ''];

        foreach ($sites->take(5) as $index => $site) {
            $customerName = trim((string) ($site->customer?->name ?? ''));
            $label = trim((string) $site->name) !== '' ? (string) $site->name : 'Sem nome';

            $lines[] = sprintf(
                '%d. %s — %s%s',
                $index + 1,
                (string) $site->code,
                $label,
                $customerName !== '' ? ' ('.$customerName.')' : ''
            );
        }

        $lines[] = 'Responde com o numero.';

        return implode("\n", $lines);
    }

    private function resolveActorUser(TelegramUserLink $link): ?User
    {
        $userId = (int) $link->user_id;
        if ($userId <= 0) {
            return null;
        }

        return User::query()
            ->where('company_id', (int) $link->company_id)
            ->where('is_active', true)
            ->whereKey($userId)
            ->first();
    }
}
