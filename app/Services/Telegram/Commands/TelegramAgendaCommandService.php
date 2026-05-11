<?php

namespace App\Services\Telegram\Commands;

use App\Models\CalendarEvent;
use App\Models\TelegramUserLink;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class TelegramAgendaCommandService
{
    public function execute(TelegramUserLink $link, string $period): string
    {
        $resolved = $this->resolvePeriod($period);
        if ($resolved === null) {
            return 'Use: /agenda hoje, /agenda amanha, /agenda semana ou /agenda mes';
        }

        $companyId = (int) $link->company_id;

        $events = CalendarEvent::query()
            ->forCompany($companyId)
            ->where('status', '!=', CalendarEvent::STATUS_CANCELLED)
            ->whereBetween('starts_at', [$resolved['from'], $resolved['to']])
            ->with([
                'customer:id,name',
                'constructionSite:id,code,name',
            ])
            ->orderBy('starts_at')
            ->limit($resolved['limit'])
            ->get();

        if ($events->isEmpty()) {
            return sprintf('Nao tem eventos na agenda para %s.', $resolved['label']);
        }

        $lines = [sprintf('Agenda de %s:', $resolved['label']), ''];

        foreach ($events as $event) {
            $lines = array_merge($lines, $this->formatEventLines($event));
            $lines[] = '';
        }

        return trim(implode("\n", $lines));
    }

    /**
     * @return array{label:string,from:CarbonImmutable,to:CarbonImmutable,limit:int}|null
     */
    private function resolvePeriod(string $period): ?array
    {
        $raw = trim($period);
        if ($raw === '') {
            return null;
        }

        $normalized = Str::of($raw)->lower()->ascii()->squish()->value();
        $now = CarbonImmutable::now();

        $weekdayRange = $this->resolveWeekdayPeriod($normalized, $now);
        if ($weekdayRange !== null) {
            return $weekdayRange;
        }

        return match ($normalized) {
            'hoje' => [
                'label' => 'hoje',
                'from' => $now->startOfDay(),
                'to' => $now->endOfDay(),
                'limit' => 10,
            ],
            'amanha' => [
                'label' => 'amanha',
                'from' => $now->addDay()->startOfDay(),
                'to' => $now->addDay()->endOfDay(),
                'limit' => 10,
            ],
            'semana' => [
                'label' => 'esta semana',
                'from' => $now->startOfWeek()->startOfDay(),
                'to' => $now->endOfWeek()->endOfDay(),
                'limit' => 15,
            ],
            'mes' => [
                'label' => 'este mes',
                'from' => $now->startOfMonth()->startOfDay(),
                'to' => $now->endOfMonth()->endOfDay(),
                'limit' => 15,
            ],
            default => null,
        };
    }

    /**
     * @return array{label:string,from:CarbonImmutable,to:CarbonImmutable,limit:int}|null
     */
    private function resolveWeekdayPeriod(string $normalized, CarbonImmutable $now): ?array
    {
        $weekdayMap = [
            'segunda feira' => CarbonImmutable::MONDAY,
            'segunda' => CarbonImmutable::MONDAY,
            'terca feira' => CarbonImmutable::TUESDAY,
            'terca' => CarbonImmutable::TUESDAY,
            'quarta feira' => CarbonImmutable::WEDNESDAY,
            'quarta' => CarbonImmutable::WEDNESDAY,
            'quinta feira' => CarbonImmutable::THURSDAY,
            'quinta' => CarbonImmutable::THURSDAY,
            'sexta feira' => CarbonImmutable::FRIDAY,
            'sexta' => CarbonImmutable::FRIDAY,
            'sabado' => CarbonImmutable::SATURDAY,
            'domingo' => CarbonImmutable::SUNDAY,
        ];

        $forceNextWeek = str_contains($normalized, 'proxima') || str_contains($normalized, 'que vem');
        $cleanNormalized = trim((string) preg_replace('/\b(proxima|que vem)\b/u', ' ', $normalized));
        $cleanNormalized = Str::of($cleanNormalized)->squish()->value();

        foreach ($weekdayMap as $label => $weekday) {
            if ($cleanNormalized !== $label) {
                continue;
            }

            $day = $this->nextOrSameWeekday($now->startOfDay(), $weekday, $forceNextWeek);

            return [
                'label' => $label,
                'from' => $day->startOfDay(),
                'to' => $day->endOfDay(),
                'limit' => 10,
            ];
        }

        return null;
    }

    private function nextOrSameWeekday(CarbonImmutable $baseDate, int $weekday, bool $forceNextWeek = false): CarbonImmutable
    {
        $current = (int) $baseDate->dayOfWeekIso;
        $target = $weekday === 0 ? 7 : $weekday;
        $diff = ($target - $current + 7) % 7;
        if ($forceNextWeek && $diff === 0) {
            $diff = 7;
        }

        return $baseDate->addDays($diff);
    }

    /**
     * @return list<string>
     */
    private function formatEventLines(CalendarEvent $event): array
    {
        $timeLabel = $event->all_day ? 'Todo o dia' : $event->starts_at?->format('H:i');
        $title = trim((string) $event->title) !== '' ? trim((string) $event->title) : 'Evento';

        $lines = [sprintf('%s - %s', $timeLabel, $title)];

        $customerName = trim((string) ($event->customer?->name ?? ''));
        if ($customerName !== '') {
            $lines[] = sprintf('Cliente: %s', $customerName);
        }

        $siteName = trim((string) ($event->constructionSite?->name ?? ''));
        $siteCode = trim((string) ($event->constructionSite?->code ?? ''));
        if ($siteName !== '' || $siteCode !== '') {
            $lines[] = sprintf('Obra: %s', trim($siteCode.' '.$siteName));
        }

        $status = trim((string) $event->statusLabel());
        if ($status !== '') {
            $lines[] = sprintf('Estado: %s', Str::of($status)->lower()->value());
        }

        return $lines;
    }
}
