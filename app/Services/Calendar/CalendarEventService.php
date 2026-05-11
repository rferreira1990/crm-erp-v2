<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\CompanyCalendarIntegration;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CalendarEventService
{
    private const COMPANY_NEUTRAL_COLOR = '#6c757d';

    /**
     * @var list<string>
     */
    private const USER_COLOR_PALETTE = [
        '#2c7be5',
        '#00a28a',
        '#f5803e',
        '#8f6ed5',
        '#d64e8a',
        '#1f9ac9',
        '#4a7c59',
        '#f4b400',
    ];

    public function __construct(
        private readonly CalendarIntegrationResolverService $calendarIntegrationResolverService,
        private readonly CalDavCalendarSyncService $calDavCalendarSyncService
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(int $companyId, int $actorUserId, array $payload): CalendarEvent
    {
        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = DB::transaction(function () use ($companyId, $actorUserId, $payload): CalendarEvent {
            $resolved = $this->resolveRelations($companyId, $payload);
            $startsAt = Carbon::parse((string) $payload['starts_at']);
            $endsAt = $this->parseNullableDateTime($payload['ends_at'] ?? null);
            $allDay = (bool) ($payload['all_day'] ?? false);
            $effectiveEndsAt = $this->effectiveEndsAt($startsAt, $endsAt, $allDay);

            $this->validateUserAvailability(
                companyId: $companyId,
                userId: $resolved['user_id'],
                startsAt: $startsAt,
                endsAt: $effectiveEndsAt
            );

            $timestamps = $this->statusTimestamps((string) $payload['status']);

            return CalendarEvent::query()->create([
                'company_id' => $companyId,
                'user_id' => $resolved['user_id'],
                'created_by' => $actorUserId,
                'customer_id' => $resolved['customer_id'],
                'supplier_id' => $resolved['supplier_id'],
                'construction_site_id' => $resolved['construction_site_id'],
                'quote_id' => $resolved['quote_id'],
                'title' => (string) $payload['title'],
                'description' => $this->normalizeNullableString($payload['description'] ?? null),
                'type' => (string) $payload['type'],
                'status' => (string) $payload['status'],
                'priority' => (string) $payload['priority'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'completed_at' => $timestamps['completed_at'],
                'cancelled_at' => $timestamps['cancelled_at'],
                'metadata' => null,
            ]);
        });

        $calendarEvent = $calendarEvent->fresh($this->relations());
        $this->attemptSyncUpsert($calendarEvent);

        return $calendarEvent;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $companyId, int $calendarEventId, array $payload): CalendarEvent
    {
        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = DB::transaction(function () use ($companyId, $calendarEventId, $payload): CalendarEvent {
            /** @var CalendarEvent $calendarEvent */
            $calendarEvent = CalendarEvent::query()
                ->forCompany($companyId)
                ->whereKey($calendarEventId)
                ->lockForUpdate()
                ->firstOrFail();

            $resolved = $this->resolveRelations($companyId, $payload);
            $startsAt = Carbon::parse((string) $payload['starts_at']);
            $endsAt = $this->parseNullableDateTime($payload['ends_at'] ?? null);
            $allDay = (bool) ($payload['all_day'] ?? false);
            $effectiveEndsAt = $this->effectiveEndsAt($startsAt, $endsAt, $allDay);

            $this->validateUserAvailability(
                companyId: $companyId,
                userId: $resolved['user_id'],
                startsAt: $startsAt,
                endsAt: $effectiveEndsAt,
                ignoreEventId: (int) $calendarEvent->id
            );

            $timestamps = $this->statusTimestamps((string) $payload['status']);

            $calendarEvent->forceFill([
                'user_id' => $resolved['user_id'],
                'customer_id' => $resolved['customer_id'],
                'supplier_id' => $resolved['supplier_id'],
                'construction_site_id' => $resolved['construction_site_id'],
                'quote_id' => $resolved['quote_id'],
                'title' => (string) $payload['title'],
                'description' => $this->normalizeNullableString($payload['description'] ?? null),
                'type' => (string) $payload['type'],
                'status' => (string) $payload['status'],
                'priority' => (string) $payload['priority'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'all_day' => $allDay,
                'completed_at' => $timestamps['completed_at'],
                'cancelled_at' => $timestamps['cancelled_at'],
            ])->save();

            return $calendarEvent;
        });

        $calendarEvent = $calendarEvent->fresh($this->relations());
        $this->attemptSyncUpsert($calendarEvent);

        return $calendarEvent;
    }

    public function complete(int $companyId, int $calendarEventId): CalendarEvent
    {
        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = DB::transaction(function () use ($companyId, $calendarEventId): CalendarEvent {
            /** @var CalendarEvent $calendarEvent */
            $calendarEvent = CalendarEvent::query()
                ->forCompany($companyId)
                ->whereKey($calendarEventId)
                ->lockForUpdate()
                ->firstOrFail();

            $calendarEvent->forceFill([
                'status' => CalendarEvent::STATUS_COMPLETED,
                'completed_at' => now(),
                'cancelled_at' => null,
            ])->save();

            return $calendarEvent;
        });

        $calendarEvent = $calendarEvent->fresh($this->relations());
        $this->attemptSyncUpsert($calendarEvent);

        return $calendarEvent;
    }

    public function cancel(int $companyId, int $calendarEventId): CalendarEvent
    {
        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = DB::transaction(function () use ($companyId, $calendarEventId): CalendarEvent {
            /** @var CalendarEvent $calendarEvent */
            $calendarEvent = CalendarEvent::query()
                ->forCompany($companyId)
                ->whereKey($calendarEventId)
                ->lockForUpdate()
                ->firstOrFail();

            $calendarEvent->forceFill([
                'status' => CalendarEvent::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'completed_at' => null,
            ])->save();

            return $calendarEvent;
        });

        $calendarEvent = $calendarEvent->fresh($this->relations());
        $this->attemptSyncUpsert($calendarEvent);

        return $calendarEvent;
    }

    public function delete(int $companyId, int $calendarEventId): void
    {
        /** @var CalendarEvent $calendarEvent */
        $calendarEvent = CalendarEvent::query()
            ->forCompany($companyId)
            ->with($this->relations())
            ->whereKey($calendarEventId)
            ->firstOrFail();

        $this->attemptSyncDelete($calendarEvent);
        $calendarEvent->delete();
    }

    /**
     * @param array<string, mixed> $filters
     * @return list<array<string, mixed>>
     */
    public function eventsForCalendar(int $companyId, array $filters): array
    {
        $query = CalendarEvent::query()
            ->forCompany($companyId)
            ->with($this->relations());

        $query = $this->applyFilters($query, $filters);

        return $query
            ->orderBy('starts_at')
            ->orderBy('id')
            ->get()
            ->map(function (CalendarEvent $event): array {
                $responsibleColor = $this->resolveEventColor($event);
                $textColor = $this->contrastTextColor($responsibleColor);

                return [
                    'id' => (int) $event->id,
                    'title' => $event->title,
                    'start' => optional($event->starts_at)->toIso8601String(),
                    'end' => optional($event->ends_at)->toIso8601String(),
                    'allDay' => (bool) $event->all_day,
                    'backgroundColor' => $responsibleColor,
                    'borderColor' => $responsibleColor,
                    'textColor' => $textColor,
                    'classNames' => [$this->statusClass($event->status), $this->priorityClass($event->priority)],
                    'extendedProps' => [
                        'description' => $event->description,
                        'type' => $event->type,
                        'type_label' => $event->typeLabel(),
                        'status' => $event->status,
                        'status_label' => $event->statusLabel(),
                        'priority' => $event->priority,
                        'priority_label' => $event->priorityLabel(),
                        'responsible_id' => $event->user_id,
                        'responsible_name' => $event->user?->name,
                        'responsible_color' => $responsibleColor,
                        'customer_id' => $event->customer_id,
                        'customer_name' => $event->customer?->name,
                        'supplier_id' => $event->supplier_id,
                        'supplier_name' => $event->supplier?->name,
                        'construction_site_id' => $event->construction_site_id,
                        'construction_site_name' => $event->constructionSite?->name,
                        'construction_site_code' => $event->constructionSite?->code,
                        'quote_id' => $event->quote_id,
                        'quote_number' => $event->quote?->number,
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int|null,name:string,color:string,text_color:string}>
     */
    public function buildResponsibleLegend(int $companyId): array
    {
        /** @var Collection<int, User> $users */
        $users = User::query()
            ->where('is_super_admin', false)
            ->where('company_id', $companyId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'calendar_color']);

        $legend = $users->map(function (User $user): array {
            return [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'color' => $this->resolveUserColor($user),
                'text_color' => $this->contrastTextColor($this->resolveUserColor($user)),
            ];
        })->values()->all();

        $legend[] = [
            'id' => null,
            'name' => 'Sem responsavel',
            'color' => self::COMPANY_NEUTRAL_COLOR,
            'text_color' => $this->contrastTextColor(self::COMPANY_NEUTRAL_COLOR),
        ];

        return $legend;
    }

    /**
     * @return array{
     *   enabled:bool,
     *   executed:bool,
     *   message:string,
     *   attempted:int,
     *   synced:int,
     *   failed:int,
     *   skipped:int
     * }
     */
    public function autoSyncCompanyCalendarIfDue(int $companyId, bool $force = false): array
    {
        $integration = $this->calendarIntegrationResolverService->resolveCompanyMainIntegration($companyId);
        if (! $integration instanceof CompanyCalendarIntegration) {
            return [
                'enabled' => false,
                'executed' => false,
                'message' => 'Integracao CalDAV nao configurada.',
                'attempted' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $intervalSeconds = max(60, (int) config('calendar.caldav.auto_sync_interval_seconds', 300));
        $lastSyncAt = $integration->last_sync_at;
        if (
            ! $force
            && $lastSyncAt
            && now()->diffInSeconds($lastSyncAt) < $intervalSeconds
        ) {
            return [
                'enabled' => true,
                'executed' => false,
                'message' => 'Sincronizacao recente. Nova tentativa em breve.',
                'attempted' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        try {
            $result = $this->calDavCalendarSyncService->syncDueCompanyEvents($integration, $companyId);

            return [
                'enabled' => true,
                'executed' => true,
                'message' => sprintf(
                    'CalDAV atualizado: %d sincronizados, %d falhas.',
                    (int) $result['synced'],
                    (int) $result['failed']
                ),
                'attempted' => (int) $result['attempted'],
                'synced' => (int) $result['synced'],
                'failed' => (int) $result['failed'],
                'skipped' => (int) $result['skipped'],
            ];
        } catch (Throwable $exception) {
            Log::warning('Calendar auto sync failed', [
                'company_id' => $companyId,
                'integration_id' => (int) $integration->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'enabled' => true,
                'executed' => true,
                'message' => 'Falha na sincronizacao automatica CalDAV.',
                'attempted' => 0,
                'synced' => 0,
                'failed' => 1,
                'skipped' => 0,
            ];
        }
    }

    public function validateUserAvailability(
        int $companyId,
        ?int $userId,
        Carbon $startsAt,
        Carbon $endsAt,
        ?int $ignoreEventId = null
    ): void {
        if ($userId === null || $userId <= 0) {
            return;
        }

        $query = CalendarEvent::query()
            ->forCompany($companyId)
            ->where('user_id', $userId)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELLED)
            ->where('starts_at', '<', $endsAt)
            ->where(function (Builder $conflictQuery) use ($startsAt): void {
                $conflictQuery
                    ->where('ends_at', '>', $startsAt)
                    ->orWhere(function (Builder $nullEndQuery) use ($startsAt): void {
                        $nullEndQuery
                            ->whereNull('ends_at')
                            ->where('starts_at', '>', $startsAt->copy()->subHour());
                    });
            });

        if ($ignoreEventId !== null && $ignoreEventId > 0) {
            $query->whereKeyNot($ignoreEventId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'O utilizador ja tem uma tarefa/evento nesse horario.',
            ]);
        }
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        $start = trim((string) ($filters['start'] ?? ''));
        $end = trim((string) ($filters['end'] ?? ''));

        $type = trim((string) ($filters['type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $responsibleId = (int) ($filters['user_id'] ?? 0);
        $customerId = (int) ($filters['customer_id'] ?? 0);
        $constructionSiteId = (int) ($filters['construction_site_id'] ?? 0);

        if ($start !== '' && $end !== '') {
            $query->where(function (Builder $dateQuery) use ($start, $end): void {
                $dateQuery
                    ->where('starts_at', '<', Carbon::parse($end))
                    ->where(function (Builder $endQuery) use ($start): void {
                        $endQuery
                            ->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', Carbon::parse($start));
                    });
            });
        }

        if ($type !== '' && in_array($type, CalendarEvent::types(), true)) {
            $query->where('type', $type);
        }

        if ($status !== '' && in_array($status, CalendarEvent::statuses(), true)) {
            $query->where('status', $status);
        }

        if ($responsibleId > 0) {
            $query->where('user_id', $responsibleId);
        }

        if ($customerId > 0) {
            $query->where('customer_id', $customerId);
        }

        if ($constructionSiteId > 0) {
            $query->where('construction_site_id', $constructionSiteId);
        }

        return $query;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{
     *   user_id:int|null,
     *   customer_id:int|null,
     *   supplier_id:int|null,
     *   construction_site_id:int|null,
     *   quote_id:int|null
     * }
     */
    private function resolveRelations(int $companyId, array $payload): array
    {
        $userId = isset($payload['user_id']) ? (int) $payload['user_id'] : null;
        $customerId = isset($payload['customer_id']) ? (int) $payload['customer_id'] : null;
        $supplierId = isset($payload['supplier_id']) ? (int) $payload['supplier_id'] : null;
        $constructionSiteId = isset($payload['construction_site_id']) ? (int) $payload['construction_site_id'] : null;
        $quoteId = isset($payload['quote_id']) ? (int) $payload['quote_id'] : null;

        if ($userId !== null && $userId > 0) {
            User::query()
                ->where('is_super_admin', false)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereKey($userId)
                ->firstOrFail();
        } else {
            $userId = null;
        }

        if ($customerId !== null && $customerId > 0) {
            Customer::query()
                ->forCompany($companyId)
                ->whereKey($customerId)
                ->firstOrFail();
        } else {
            $customerId = null;
        }

        if ($supplierId !== null && $supplierId > 0) {
            Supplier::query()
                ->forCompany($companyId)
                ->whereKey($supplierId)
                ->firstOrFail();
        } else {
            $supplierId = null;
        }

        if ($constructionSiteId !== null && $constructionSiteId > 0) {
            ConstructionSite::query()
                ->forCompany($companyId)
                ->whereKey($constructionSiteId)
                ->firstOrFail();
        } else {
            $constructionSiteId = null;
        }

        if ($quoteId !== null && $quoteId > 0) {
            Quote::query()
                ->forCompany($companyId)
                ->whereKey($quoteId)
                ->firstOrFail();
        } else {
            $quoteId = null;
        }

        return [
            'user_id' => $userId,
            'customer_id' => $customerId,
            'supplier_id' => $supplierId,
            'construction_site_id' => $constructionSiteId,
            'quote_id' => $quoteId,
        ];
    }

    /**
     * @return array{completed_at:Carbon|null,cancelled_at:Carbon|null}
     */
    private function statusTimestamps(string $status): array
    {
        if ($status === CalendarEvent::STATUS_COMPLETED) {
            return [
                'completed_at' => now(),
                'cancelled_at' => null,
            ];
        }

        if ($status === CalendarEvent::STATUS_CANCELLED) {
            return [
                'completed_at' => null,
                'cancelled_at' => now(),
            ];
        }

        return [
            'completed_at' => null,
            'cancelled_at' => null,
        ];
    }

    private function statusClass(string $status): string
    {
        return match ($status) {
            CalendarEvent::STATUS_COMPLETED => 'bg-success-subtle text-success border-success',
            CalendarEvent::STATUS_CANCELLED => 'bg-danger-subtle text-danger border-danger',
            default => 'bg-primary-subtle text-primary border-primary',
        };
    }

    private function priorityClass(string $priority): string
    {
        return match ($priority) {
            CalendarEvent::PRIORITY_HIGH => 'border border-danger',
            CalendarEvent::PRIORITY_LOW => 'border border-info',
            default => 'border border-secondary',
        };
    }

    /**
     * @return list<string>
     */
    private function relations(): array
    {
        return [
            'user:id,name,calendar_color',
            'customer:id,name',
            'supplier:id,name',
            'constructionSite:id,code,name',
            'quote:id,number',
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function parseNullableDateTime(mixed $value): ?Carbon
    {
        $normalized = $this->normalizeNullableString($value);
        if ($normalized === null) {
            return null;
        }

        return Carbon::parse($normalized);
    }

    private function effectiveEndsAt(Carbon $startsAt, ?Carbon $endsAt, bool $allDay): Carbon
    {
        if ($endsAt !== null) {
            return $endsAt;
        }

        return $allDay
            ? $startsAt->copy()->addDay()
            : $startsAt->copy()->addHour();
    }

    private function resolveEventColor(CalendarEvent $event): string
    {
        if (! $event->user_id || ! $event->user) {
            return self::COMPANY_NEUTRAL_COLOR;
        }

        return $this->resolveUserColor($event->user);
    }

    private function resolveUserColor(User $user): string
    {
        $custom = trim((string) ($user->calendar_color ?? ''));
        if ($this->isValidHexColor($custom)) {
            return $this->normalizeHexColor($custom);
        }

        $palette = self::USER_COLOR_PALETTE;
        $index = ((int) $user->id) % count($palette);

        return $palette[$index];
    }

    private function contrastTextColor(string $hexColor): string
    {
        $hex = ltrim($this->normalizeHexColor($hexColor), '#');
        if (strlen($hex) !== 6) {
            return '#ffffff';
        }

        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
        $luminance = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

        return $luminance >= 155 ? '#1f2937' : '#ffffff';
    }

    private function isValidHexColor(string $value): bool
    {
        return (bool) preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value);
    }

    private function normalizeHexColor(string $value): string
    {
        $value = strtolower(trim($value));
        if (! str_starts_with($value, '#')) {
            return self::COMPANY_NEUTRAL_COLOR;
        }

        if (strlen($value) === 4) {
            return '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
        }

        return strlen($value) === 7 ? $value : self::COMPANY_NEUTRAL_COLOR;
    }

    private function attemptSyncUpsert(?CalendarEvent $event): void
    {
        if (! $event) {
            return;
        }

        try {
            $integration = $this->calendarIntegrationResolverService->resolveForEvent($event);
            if (! $integration instanceof CompanyCalendarIntegration) {
                return;
            }

            $this->calDavCalendarSyncService->syncEvent($integration, $event);
        } catch (Throwable $exception) {
            Log::warning('Calendar CalDAV sync upsert failed', [
                'company_id' => (int) $event->company_id,
                'event_id' => (int) $event->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function attemptSyncDelete(?CalendarEvent $event): void
    {
        if (! $event) {
            return;
        }

        try {
            $integration = $this->calendarIntegrationResolverService->resolveForEvent($event);
            if (! $integration instanceof CompanyCalendarIntegration) {
                return;
            }

            $this->calDavCalendarSyncService->deleteEvent($integration, $event);
        } catch (Throwable $exception) {
            Log::warning('Calendar CalDAV sync delete failed', [
                'company_id' => (int) $event->company_id,
                'event_id' => (int) $event->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
