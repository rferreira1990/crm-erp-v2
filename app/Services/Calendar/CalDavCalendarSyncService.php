<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\CalendarEventExternalSync;
use App\Models\CompanyCalendarIntegration;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CalDavCalendarSyncService
{
    public function __construct(
        private readonly CalendarEventIcsBuilderService $icsBuilderService
    ) {
    }

    /**
     * @return array{ok:bool,message:string}
     */
    public function testConnection(CompanyCalendarIntegration $integration): array
    {
        if (! $this->isSafeHttpsUrl((string) $integration->calendar_url)) {
            return [
                'ok' => false,
                'message' => 'Calendar URL invalido. Use HTTPS publico.',
            ];
        }

        $password = $this->resolvePassword($integration);
        if ($password === null) {
            return [
                'ok' => false,
                'message' => 'Password da integracao nao configurada.',
            ];
        }

        try {
            $response = Http::timeout(15)
                ->withBasicAuth((string) $integration->username, $password)
                ->withHeaders(['Depth' => '0'])
                ->send('PROPFIND', (string) $integration->calendar_url);
        } catch (ConnectionException) {
            return [
                'ok' => false,
                'message' => 'Falha de ligacao ao servidor CalDAV.',
            ];
        } catch (Throwable $exception) {
            Log::warning('CalDAV test connection failed', [
                'company_id' => (int) $integration->company_id,
                'integration_id' => (int) $integration->id,
                'error' => $exception->getMessage(),
            ]);

            return [
                'ok' => false,
                'message' => 'Nao foi possivel testar a ligacao CalDAV.',
            ];
        }

        if (in_array($response->status(), [200, 207], true)) {
            return [
                'ok' => true,
                'message' => 'Ligacao CalDAV validada com sucesso.',
            ];
        }

        return [
            'ok' => false,
            'message' => 'Ligacao CalDAV rejeitada pelo servidor (HTTP '.$response->status().').',
        ];
    }

    /**
     * @return array{attempted:int,synced:int,failed:int,skipped:int}
     */
    public function syncDueCompanyEvents(CompanyCalendarIntegration $integration, int $companyId): array
    {
        if ((int) $integration->company_id !== $companyId) {
            throw new RuntimeException('Calendar integration company mismatch.');
        }

        $pastDays = (int) config('calendar.caldav.auto_sync_past_days', 45);
        $futureDays = (int) config('calendar.caldav.auto_sync_future_days', 365);
        $limit = (int) config('calendar.caldav.auto_sync_limit', 300);

        $windowStart = Carbon::now()->subDays(max(1, $pastDays));
        $windowEnd = Carbon::now()->addDays(max(1, $futureDays));

        $events = CalendarEvent::query()
            ->forCompany($companyId)
            ->whereBetween('starts_at', [$windowStart, $windowEnd])
            ->orderBy('starts_at')
            ->limit(max(10, $limit))
            ->get();

        if ($events->isEmpty()) {
            return [
                'attempted' => 0,
                'synced' => 0,
                'failed' => 0,
                'skipped' => 0,
            ];
        }

        $syncRows = CalendarEventExternalSync::query()
            ->forCompany($companyId)
            ->where('integration_id', $integration->id)
            ->whereIn('calendar_event_id', $events->pluck('id')->all())
            ->get()
            ->keyBy('calendar_event_id');

        $attempted = 0;
        $synced = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($events as $event) {
            $syncRow = $syncRows->get((int) $event->id);
            $mustSync = false;

            if (! $syncRow) {
                $mustSync = true;
            } elseif ($syncRow->sync_status !== CalendarEventExternalSync::STATUS_SYNCED) {
                $mustSync = true;
            } elseif (! $syncRow->last_synced_at || $event->updated_at?->gt($syncRow->last_synced_at)) {
                $mustSync = true;
            }

            if (! $mustSync) {
                $skipped++;

                continue;
            }

            $attempted++;

            try {
                $this->syncEvent($integration, $event);
            } catch (Throwable) {
                $failed++;

                continue;
            }

            $latestStatus = CalendarEventExternalSync::query()
                ->forCompany($companyId)
                ->where('integration_id', $integration->id)
                ->where('calendar_event_id', $event->id)
                ->value('sync_status');

            if ($latestStatus === CalendarEventExternalSync::STATUS_SYNCED) {
                $synced++;
            } else {
                $failed++;
            }
        }

        return [
            'attempted' => $attempted,
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
        ];
    }

    public function syncEvent(CompanyCalendarIntegration $integration, CalendarEvent $event): void
    {
        $this->validateIntegrationAndEvent($integration, $event);

        $password = $this->resolvePassword($integration);
        if ($password === null) {
            $this->markFailed($integration, $event, 'Password da integracao nao configurada.');

            return;
        }

        $uid = $this->icsBuilderService->eventUid($event);
        $ics = $this->icsBuilderService->build($event);
        $sync = $this->findOrCreateSync($integration, $event, $uid);
        $targetHref = $sync->external_href ?: $this->defaultHref($integration, $uid);

        try {
            $request = Http::timeout(20)
                ->withBasicAuth((string) $integration->username, $password)
                ->withHeaders([
                    'Content-Type' => 'text/calendar; charset=utf-8',
                ]);

            if (is_string($sync->external_etag) && trim($sync->external_etag) !== '') {
                $request = $request->withHeaders(['If-Match' => $sync->external_etag]);
            }

            $response = $request->send('PUT', $targetHref, ['body' => $ics]);
        } catch (Throwable $exception) {
            $this->markFailed($integration, $event, 'Falha ao enviar evento para CalDAV.');
            Log::warning('CalDAV sync PUT failed', [
                'company_id' => (int) $integration->company_id,
                'integration_id' => (int) $integration->id,
                'event_id' => (int) $event->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! in_array($response->status(), [200, 201, 204], true)) {
            $this->markFailed(
                $integration,
                $event,
                'Servidor CalDAV rejeitou sincronizacao (HTTP '.$response->status().').'
            );

            return;
        }

        $sync->forceFill([
            'external_uid' => $uid,
            'external_href' => $targetHref,
            'external_etag' => $response->header('ETag') ?: $sync->external_etag,
            'last_synced_at' => now(),
            'sync_status' => CalendarEventExternalSync::STATUS_SYNCED,
            'last_error' => null,
        ])->save();

        $integration->forceFill([
            'last_sync_at' => now(),
        ])->save();
    }

    public function deleteEvent(CompanyCalendarIntegration $integration, CalendarEvent $event): void
    {
        $this->validateIntegrationAndEvent($integration, $event);

        $sync = CalendarEventExternalSync::query()
            ->forCompany((int) $event->company_id)
            ->where('integration_id', $integration->id)
            ->where('calendar_event_id', $event->id)
            ->first();

        if (! $sync || ! is_string($sync->external_href) || trim($sync->external_href) === '') {
            return;
        }

        $password = $this->resolvePassword($integration);
        if ($password === null) {
            $this->markFailed($integration, $event, 'Password da integracao nao configurada.');

            return;
        }

        try {
            $response = Http::timeout(20)
                ->withBasicAuth((string) $integration->username, $password)
                ->send('DELETE', $sync->external_href);
        } catch (Throwable $exception) {
            $this->markFailed($integration, $event, 'Falha ao remover evento no CalDAV.');
            Log::warning('CalDAV delete failed', [
                'company_id' => (int) $integration->company_id,
                'integration_id' => (int) $integration->id,
                'event_id' => (int) $event->id,
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        if (! in_array($response->status(), [200, 202, 204, 404], true)) {
            $this->markFailed(
                $integration,
                $event,
                'Servidor CalDAV rejeitou remocao (HTTP '.$response->status().').'
            );

            return;
        }

        $sync->forceFill([
            'sync_status' => CalendarEventExternalSync::STATUS_DELETED,
            'last_synced_at' => now(),
            'last_error' => null,
        ])->save();

        $integration->forceFill([
            'last_sync_at' => now(),
        ])->save();
    }

    /**
     * @return array{synced:int,failed:int}
     */
    public function syncCompanyEvents(CompanyCalendarIntegration $integration, int $companyId): array
    {
        if ((int) $integration->company_id !== $companyId) {
            throw new RuntimeException('Calendar integration company mismatch.');
        }

        $synced = 0;
        $failed = 0;

        $events = CalendarEvent::query()
            ->forCompany($companyId)
            ->orderByDesc('starts_at')
            ->limit(300)
            ->get();

        foreach ($events as $event) {
            try {
                $this->syncEvent($integration, $event);

                $latest = CalendarEventExternalSync::query()
                    ->forCompany($companyId)
                    ->where('integration_id', $integration->id)
                    ->where('calendar_event_id', $event->id)
                    ->value('sync_status');

                if ($latest === CalendarEventExternalSync::STATUS_SYNCED) {
                    $synced++;
                } else {
                    $failed++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        return [
            'synced' => $synced,
            'failed' => $failed,
        ];
    }

    private function findOrCreateSync(
        CompanyCalendarIntegration $integration,
        CalendarEvent $event,
        string $uid
    ): CalendarEventExternalSync {
        return CalendarEventExternalSync::query()->firstOrCreate(
            [
                'company_id' => $event->company_id,
                'integration_id' => $integration->id,
                'calendar_event_id' => $event->id,
            ],
            [
                'external_uid' => $uid,
                'sync_status' => CalendarEventExternalSync::STATUS_PENDING,
            ]
        );
    }

    private function defaultHref(CompanyCalendarIntegration $integration, string $uid): string
    {
        return rtrim((string) $integration->calendar_url, '/').'/'.$uid.'.ics';
    }

    private function resolvePassword(CompanyCalendarIntegration $integration): ?string
    {
        $password = $integration->getAttribute('password');
        if (! is_string($password)) {
            return null;
        }

        $normalized = trim($password);

        return $normalized !== '' ? $normalized : null;
    }

    private function markFailed(
        CompanyCalendarIntegration $integration,
        CalendarEvent $event,
        string $errorMessage
    ): void {
        $uid = $this->icsBuilderService->eventUid($event);

        CalendarEventExternalSync::query()->updateOrCreate(
            [
                'company_id' => $event->company_id,
                'integration_id' => $integration->id,
                'calendar_event_id' => $event->id,
            ],
            [
                'external_uid' => $uid,
                'sync_status' => CalendarEventExternalSync::STATUS_FAILED,
                'last_error' => mb_substr($errorMessage, 0, 5000),
                'last_synced_at' => now(),
            ]
        );
    }

    private function validateIntegrationAndEvent(
        CompanyCalendarIntegration $integration,
        CalendarEvent $event
    ): void {
        if ((int) $integration->company_id !== (int) $event->company_id) {
            throw new RuntimeException('Calendar integration/event company mismatch.');
        }

        if (! $this->isSafeHttpsUrl((string) $integration->calendar_url)) {
            throw new RuntimeException('Unsafe CalDAV URL.');
        }
    }

    private function isSafeHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if ($scheme !== 'https' || $host === '') {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (str_starts_with($url, 'file://')) {
            return false;
        }

        return true;
    }
}
