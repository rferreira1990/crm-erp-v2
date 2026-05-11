<?php

namespace App\Services\Telegram\Commands;

use App\Models\CalendarEvent;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\Supplier;
use App\Models\TelegramPendingSelection;
use App\Models\TelegramUserLink;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use App\Services\Telegram\TelegramPendingSelectionService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TelegramCalendarEventDraftService
{
    public function __construct(
        private readonly TelegramPendingSelectionService $pendingSelectionService,
        private readonly CalendarEventService $calendarEventService
    ) {
    }

    /**
     * @param array<string, mixed>|null $aiData
     * @return array{status:string,message:string}
     */
    public function prepareDraftFromAi(
        TelegramUserLink $link,
        int|string $chatId,
        ?array $aiData,
        ?string $originalMessage = null
    ): array {
        $actorUser = $this->resolveActorUser($link);
        if (! $actorUser || ! $actorUser->can('company.calendar.create')) {
            return [
                'status' => 'forbidden',
                'message' => 'Nao tem permissao para criar eventos na agenda.',
            ];
        }

        if (! is_array($aiData) || $aiData === []) {
            return [
                'status' => 'invalid',
                'message' => 'Nao consegui preparar o evento. Indique data e hora no pedido.',
            ];
        }

        $dateText = $this->normalizeNullableString($aiData['date_text'] ?? null);
        $timeText = $this->normalizeNullableString($aiData['time_text'] ?? null);
        $endsAtText = $this->normalizeNullableString($aiData['ends_at_text'] ?? null);
        $startsAtText = $this->normalizeNullableString($aiData['starts_at_text'] ?? null);
        $allDay = $this->normalizeNullableBool($aiData['all_day'] ?? null);

        if ($startsAtText !== null) {
            [$dateTextFromStart, $timeTextFromStart] = $this->splitStartsAtText($startsAtText);
            $dateText = $dateText ?? $dateTextFromStart;
            $timeText = $timeText ?? $timeTextFromStart;
        }

        if ($originalMessage !== null) {
            $fallback = $this->extractDateTimeHintsFromText($originalMessage);
            $dateText = $dateText ?? $fallback['date_text'];
            $timeText = $timeText ?? $fallback['start_time_text'];
            $endsAtText = $endsAtText ?? $fallback['end_time_text'];
            $allDay = $allDay ?? $fallback['all_day'];
        }

        if ($allDay === null && $dateText !== null && $timeText === null) {
            $allDay = true;
        }

        $allDay = $allDay ?? false;

        if ($dateText === null) {
            return [
                'status' => 'missing_date_time',
                'message' => 'Faltam dados. Indique data e hora no pedido (ex.: "amanha as 10h").',
            ];
        }

        if (! $allDay && $timeText === null) {
            return [
                'status' => 'missing_date_time',
                'message' => 'Faltam dados. Indique data e hora no pedido (ex.: "amanha as 10h").',
            ];
        }

        $startsAt = $allDay
            ? $this->resolveDate($dateText)?->startOfDay()
            : $this->resolveStartDateTime($dateText, (string) $timeText);
        if (! $startsAt) {
            return [
                'status' => 'invalid_date_time',
                'message' => 'Nao consegui interpretar a data/hora. Tente algo como "amanha as 10h".',
            ];
        }

        $endsAt = $allDay
            ? null
            : $this->resolveEndDateTime($endsAtText, $startsAt);

        $companyId = (int) $link->company_id;
        $resolvedCustomer = $this->resolveUniqueCustomer($companyId, $this->normalizeNullableString($aiData['customer_term'] ?? null));
        if (! $resolvedCustomer['ok']) {
            return ['status' => 'ambiguous_customer', 'message' => $resolvedCustomer['message']];
        }

        $resolvedSupplier = $this->resolveUniqueSupplier($companyId, $this->normalizeNullableString($aiData['supplier_term'] ?? null));
        if (! $resolvedSupplier['ok']) {
            return ['status' => 'ambiguous_supplier', 'message' => $resolvedSupplier['message']];
        }

        $resolvedSite = $this->resolveUniqueConstructionSite($companyId, $this->normalizeNullableString($aiData['construction_site_term'] ?? null));
        if (! $resolvedSite['ok']) {
            return ['status' => 'ambiguous_site', 'message' => $resolvedSite['message']];
        }

        $resolvedResponsible = $this->resolveUniqueResponsibleUser($companyId, $this->normalizeNullableString($aiData['assigned_user_term'] ?? null));
        if (! $resolvedResponsible['ok']) {
            return ['status' => 'ambiguous_user', 'message' => $resolvedResponsible['message']];
        }

        $type = $this->normalizeType($this->normalizeNullableString($aiData['type'] ?? null));
        $priority = $this->normalizePriority($this->normalizeNullableString($aiData['priority'] ?? null));
        $requestedTitle = $this->extractRequestedTitleFromOriginalMessage($originalMessage);
        $title = $this->buildTitle(
            $this->normalizeNullableString($aiData['title'] ?? null),
            $requestedTitle,
            $type,
            $resolvedSite['model'],
            $resolvedCustomer['model'],
            $resolvedSupplier['model']
        );
        $description = $this->normalizeNullableString($aiData['description'] ?? null);

        $calendarPayload = [
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'status' => CalendarEvent::STATUS_PENDING,
            'priority' => $priority,
            'starts_at' => $startsAt->toDateTimeString(),
            'ends_at' => $endsAt?->toDateTimeString(),
            'all_day' => $allDay,
            'user_id' => (int) ($resolvedResponsible['model']?->id ?? $actorUser->id),
            'customer_id' => $resolvedCustomer['model']?->id,
            'supplier_id' => $resolvedSupplier['model']?->id,
            'construction_site_id' => $resolvedSite['model']?->id,
            'quote_id' => null,
        ];

        $this->pendingSelectionService->createSelection(
            link: $link,
            chatId: $chatId,
            type: TelegramPendingSelectionService::TYPE_CALENDAR_EVENT_CREATE,
            payload: [
                'calendar_event' => $calendarPayload,
            ],
            ttlMinutes: 10
        );

        return [
            'status' => 'pending_confirmation',
            'message' => $this->buildPreviewMessage(
                payload: $calendarPayload,
                site: $resolvedSite['model'],
                customer: $resolvedCustomer['model'],
                supplier: $resolvedSupplier['model'],
                responsible: $resolvedResponsible['model'] ?? $actorUser
            ),
        ];
    }

    /**
     * @return array{status:string,message:string}
     */
    public function confirmCreation(TelegramUserLink $link, TelegramPendingSelection $selection): array
    {
        if ($selection->expires_at->isPast()) {
            $this->pendingSelectionService->consumeSelection($selection);

            return [
                'status' => 'expired',
                'message' => 'Pedido expirado. Faca o pedido novamente.',
            ];
        }

        $actorUser = $this->resolveActorUser($link);
        if (! $actorUser || ! $actorUser->can('company.calendar.create')) {
            $this->pendingSelectionService->consumeSelection($selection);

            return [
                'status' => 'forbidden',
                'message' => 'Nao tem permissao para criar eventos na agenda.',
            ];
        }

        $payload = is_array($selection->payload) ? $selection->payload : [];
        $calendarPayload = is_array($payload['calendar_event'] ?? null) ? $payload['calendar_event'] : null;

        if (! is_array($calendarPayload)) {
            $this->pendingSelectionService->consumeSelection($selection);

            return [
                'status' => 'invalid',
                'message' => 'Pedido invalido. Faca o pedido novamente.',
            ];
        }

        try {
            $event = $this->calendarEventService->create(
                companyId: (int) $link->company_id,
                actorUserId: (int) $actorUser->id,
                payload: $calendarPayload
            );
        } catch (ValidationException $exception) {
            $this->pendingSelectionService->consumeSelection($selection);

            $firstMessage = collect($exception->errors())->flatten()->first();

            return [
                'status' => 'validation_error',
                'message' => is_string($firstMessage) && $firstMessage !== ''
                    ? $firstMessage
                    : 'Nao foi possivel criar o evento nesse horario.',
            ];
        } catch (\Throwable) {
            $this->pendingSelectionService->consumeSelection($selection);

            return [
                'status' => 'error',
                'message' => 'Nao foi possivel criar o evento agora. Tente novamente.',
            ];
        }

        $this->pendingSelectionService->consumeSelection($selection);

        return [
            'status' => 'created',
            'message' => sprintf(
                "Evento criado com sucesso.\nTitulo: %s\nData/hora: %s",
                (string) $event->title,
                $event->starts_at?->format('d/m/Y H:i') ?? '-'
            ),
        ];
    }

    private function resolveActorUser(TelegramUserLink $link): ?User
    {
        return User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->where('company_id', (int) $link->company_id)
            ->whereKey((int) $link->user_id)
            ->first();
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableBool(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = Str::of($value)->lower()->ascii()->squish()->value();
            if (in_array($normalized, ['true', '1', 'sim', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['false', '0', 'nao', 'no'], true)) {
                return false;
            }
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        return null;
    }

    /**
     * @return array{0:string|null,1:string|null}
     */
    private function splitStartsAtText(string $startsAtText): array
    {
        $normalized = trim($startsAtText);
        if ($normalized === '') {
            return [null, null];
        }

        if (preg_match('/(.+?)\s+(?:as|às)\s+(.+)$/iu', $normalized, $matches) === 1) {
            return [
                $this->normalizeNullableString($matches[1] ?? null),
                $this->normalizeNullableString($matches[2] ?? null),
            ];
        }

        return [$normalized, null];
    }

    private function resolveStartDateTime(string $dateText, string $timeText): ?CarbonImmutable
    {
        $date = $this->resolveDate($dateText);
        $time = $this->resolveTime($timeText);

        if ($date === null || $time === null) {
            return null;
        }

        return $date->setTime($time['hour'], $time['minute'], 0);
    }

    private function resolveEndDateTime(?string $endsAtText, CarbonImmutable $startsAt): ?CarbonImmutable
    {
        if ($endsAtText === null) {
            return null;
        }

        $time = $this->resolveTime($endsAtText);
        if ($time === null) {
            return null;
        }

        $endsAt = $startsAt->setTime($time['hour'], $time['minute'], 0);
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return $startsAt->addHour();
        }

        return $endsAt;
    }

    private function resolveDate(string $dateText): ?CarbonImmutable
    {
        $normalized = Str::of($dateText)->lower()->ascii()->squish()->value();
        $today = CarbonImmutable::now()->startOfDay();
        $isNextWeekHint = preg_match('/\bproxim[ao]\b/u', $normalized) === 1;
        $normalizedForWeekday = trim((string) preg_replace('/\bproxim[ao]\b/u', '', $normalized));

        if (preg_match('/\bdaqui\s+a\s+(\d{1,2})\s+dias?\b/u', $normalized, $matches) === 1) {
            $days = (int) ($matches[1] ?? 0);
            if ($days > 0) {
                return $today->addDays($days);
            }
        }

        if (preg_match('/\bdaqui\s+a\s+(\d{1,2})\s+semanas?\b/u', $normalized, $matches) === 1) {
            $weeks = (int) ($matches[1] ?? 0);
            if ($weeks > 0) {
                return $today->addWeeks($weeks);
            }
        }

        if (
            str_contains($normalized, 'para a semana')
            || str_contains($normalized, 'semana que vem')
            || str_contains($normalized, 'proxima semana')
        ) {
            return $today->addWeek()->startOfWeek();
        }

        if (str_contains($normalized, 'hoje')) {
            return $today;
        }

        if (str_contains($normalized, 'amanha')) {
            return $today->addDay();
        }

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

        foreach ($weekdayMap as $label => $weekday) {
            if (preg_match('/\b'.preg_quote($label, '/').'\b/u', $normalizedForWeekday) === 1) {
                return $this->nextOrSameWeekday($today, $weekday, $isNextWeekHint);
            }
        }

        if (preg_match('/\b(segunda|terca|quarta|quinta|sexta|sabado|domingo)\s+que\s+vem\b/u', $normalized, $matches) === 1) {
            $weekdayFromText = (string) ($matches[1] ?? '');
            $weekdayMapShort = [
                'segunda' => CarbonImmutable::MONDAY,
                'terca' => CarbonImmutable::TUESDAY,
                'quarta' => CarbonImmutable::WEDNESDAY,
                'quinta' => CarbonImmutable::THURSDAY,
                'sexta' => CarbonImmutable::FRIDAY,
                'sabado' => CarbonImmutable::SATURDAY,
                'domingo' => CarbonImmutable::SUNDAY,
            ];

            if (isset($weekdayMapShort[$weekdayFromText])) {
                return $this->nextOrSameWeekday($today, $weekdayMapShort[$weekdayFromText], true);
            }
        }

        if (preg_match('/\b(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?\b/', $normalized, $matches) === 1) {
            $day = (int) $matches[1];
            $month = (int) $matches[2];
            $year = isset($matches[3]) ? (int) $matches[3] : (int) $today->year;
            if ($year < 100) {
                $year += 2000;
            }

            if (checkdate($month, $day, $year)) {
                return CarbonImmutable::create($year, $month, $day, 0, 0, 0);
            }
        }

        if (preg_match('/\bdia\s+(\d{1,2})\b/', $normalized, $matches) === 1) {
            $day = (int) $matches[1];
            if ($day >= 1 && $day <= 31) {
                $month = (int) $today->month;
                $year = (int) $today->year;

                if (! checkdate($month, $day, $year)) {
                    return null;
                }

                $candidate = CarbonImmutable::create($year, $month, $day, 0, 0, 0);
                if ($candidate->lt($today)) {
                    $candidate = $candidate->addMonthNoOverflow();
                }

                return $candidate->startOfDay();
            }
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $normalized) === 1) {
            try {
                return CarbonImmutable::parse($normalized)->startOfDay();
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    /**
     * @return array{hour:int,minute:int}|null
     */
    private function resolveTime(string $timeText): ?array
    {
        $normalized = Str::of($timeText)->lower()->ascii()->squish()->value();
        $normalized = $this->normalizeHourWordsToDigits($normalized);
        if ($normalized === '') {
            return null;
        }

        $inferredPeriodTime = $this->inferPeriodTime($normalized);
        if (is_array($inferredPeriodTime)) {
            return $inferredPeriodTime;
        }

        if (preg_match('/\b(\d{1,2})\s*e\s*meia\b/u', $normalized, $matches) === 1) {
            $hour = (int) ($matches[1] ?? -1);
            if ($hour >= 0 && $hour <= 23) {
                return ['hour' => $hour, 'minute' => 30];
            }
        }

        if (preg_match('/\b(\d{1,2})\s*e\s*um\s*quarto\b/u', $normalized, $matches) === 1) {
            $hour = (int) ($matches[1] ?? -1);
            if ($hour >= 0 && $hour <= 23) {
                return ['hour' => $hour, 'minute' => 15];
            }
        }

        if (preg_match('/\b(\d{1,2})\s*e\s*quarto\b/u', $normalized, $matches) === 1) {
            $hour = (int) ($matches[1] ?? -1);
            if ($hour >= 0 && $hour <= 23) {
                return ['hour' => $hour, 'minute' => 15];
            }
        }

        $withoutDates = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/u', ' ', $normalized) ?? $normalized;

        if (preg_match('/(?:^|\s)(\d{1,2})(?:[:h](\d{1,2}))?\s*h?(?:\s|$)/iu', $withoutDates, $matches) !== 1) {
            return null;
        }

        $hour = (int) ($matches[1] ?? -1);
        $minute = isset($matches[2]) ? (int) $matches[2] : 0;

        if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
            return null;
        }

        return [
            'hour' => $hour,
            'minute' => $minute,
        ];
    }

    private function normalizeHourWordsToDigits(string $text): string
    {
        $normalized = Str::of($text)->lower()->ascii()->value();

        $map = [
            'vinte e tres' => '23',
            'vinte e duas' => '22',
            'vinte e dois' => '22',
            'vinte e uma' => '21',
            'vinte e um' => '21',
            'vinte' => '20',
            'dezanove' => '19',
            'dezoito' => '18',
            'dezassete' => '17',
            'dezesseis' => '16',
            'dezasseis' => '16',
            'quinze' => '15',
            'catorze' => '14',
            'quatorze' => '14',
            'treze' => '13',
            'doze' => '12',
            'onze' => '11',
            'dez' => '10',
            'nove' => '9',
            'oito' => '8',
            'sete' => '7',
            'seis' => '6',
            'cinco' => '5',
            'quatro' => '4',
            'tres' => '3',
            'duas' => '2',
            'dois' => '2',
            'uma' => '1',
            'um' => '1',
        ];

        foreach ($map as $word => $number) {
            $normalized = preg_replace('/\b'.preg_quote($word, '/').'\b/u', $number, $normalized) ?? $normalized;
        }

        return Str::of((string) $normalized)->squish()->value();
    }

    /**
     * @return array{date_text:string|null,start_time_text:string|null,end_time_text:string|null,all_day:bool|null}
     */
    private function extractDateTimeHintsFromText(string $text): array
    {
        $normalized = Str::of($text)->lower()->ascii()->squish()->value();
        if ($normalized === '') {
            return [
                'date_text' => null,
                'start_time_text' => null,
                'end_time_text' => null,
                'all_day' => null,
            ];
        }

        $dateText = null;
        if (str_contains($normalized, 'amanha')) {
            $dateText = 'amanha';
        } elseif (str_contains($normalized, 'hoje')) {
            $dateText = 'hoje';
        }

        if ($dateText === null && preg_match('/\b(\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)\b/u', $normalized, $dateMatch) === 1) {
            $dateText = trim((string) ($dateMatch[1] ?? ''));
        }

        if ($dateText === null) {
            foreach ([
                'proxima segunda feira',
                'proxima terca feira',
                'proxima quarta feira',
                'proxima quinta feira',
                'proxima sexta feira',
                'proximo sabado',
                'proximo domingo',
                'segunda feira',
                'terca feira',
                'quarta feira',
                'quinta feira',
                'sexta feira',
                'segunda',
                'terca',
                'quarta',
                'quinta',
                'sexta',
                'sabado',
                'domingo',
            ] as $weekday) {
                if (preg_match('/\b'.preg_quote($weekday, '/').'\b/u', $normalized) === 1) {
                    $dateText = $weekday;
                    break;
                }
            }
        }

        if ($dateText === null && preg_match('/\b(segunda|terca|quarta|quinta|sexta|sabado|domingo)\s+que\s+vem\b/u', $normalized, $matches) === 1) {
            $dateText = trim((string) ($matches[0] ?? ''));
        }

        if ($dateText === null && preg_match('/\bdaqui\s+a\s+\d{1,2}\s+dias?\b/u', $normalized, $matches) === 1) {
            $dateText = trim((string) ($matches[0] ?? ''));
        }

        if ($dateText === null && preg_match('/\bdaqui\s+a\s+\d{1,2}\s+semanas?\b/u', $normalized, $matches) === 1) {
            $dateText = trim((string) ($matches[0] ?? ''));
        }

        if (
            $dateText === null
            && (
                str_contains($normalized, 'para a semana')
                || str_contains($normalized, 'semana que vem')
                || str_contains($normalized, 'proxima semana')
            )
        ) {
            $dateText = 'proxima semana';
        }

        $timeInput = preg_replace('/\b\d{1,2}[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?\b/u', ' ', $normalized) ?? $normalized;
        $timeInput = preg_replace('/\bdia\s+\d{1,2}\b/u', ' ', $timeInput) ?? $timeInput;
        $timeInput = preg_replace('/\bdaqui\s+a\s+\d{1,2}\s+dias?\b/u', ' ', $timeInput) ?? $timeInput;
        $timeInput = preg_replace('/\bdaqui\s+a\s+\d{1,2}\s+semanas?\b/u', ' ', $timeInput) ?? $timeInput;
        $timeInput = $this->normalizeHourWordsToDigits($timeInput);

        $times = [];
        $hasHalfExpression = preg_match('/\b(\d{1,2})\s*e\s*meia\b/u', $timeInput, $halfMatch) === 1;
        if ($hasHalfExpression) {
            $halfHour = (int) ($halfMatch[1] ?? -1);
            if ($halfHour >= 0 && $halfHour <= 23) {
                $times[] = sprintf('%02d:30', $halfHour);
            }
        }

        if ($hasHalfExpression) {
            $timeInput = preg_replace('/\b\d{1,2}\s*e\s*meia\b/u', ' ', $timeInput) ?? $timeInput;
        }

        $timeMatches = [];
        preg_match_all('/(?:^|\s)(\d{1,2})(?:[:h](\d{1,2}))?\s*h?(?:\s|$)/iu', $timeInput, $timeMatches, PREG_SET_ORDER);
        foreach ($timeMatches as $match) {
            $hour = isset($match[1]) ? (int) $match[1] : -1;
            $minute = isset($match[2]) ? (int) $match[2] : 0;
            if ($hour < 0 || $hour > 23 || $minute < 0 || $minute > 59) {
                continue;
            }

            $times[] = sprintf('%02d:%02d', $hour, $minute);
        }

        if ($times === []) {
            $inferred = $this->inferPeriodTime($normalized);
            if (is_array($inferred)) {
                $times[] = sprintf('%02d:%02d', $inferred['hour'], $inferred['minute']);
            }
        }

        $allDay = null;
        if (
            str_contains($normalized, 'dia todo')
            || str_contains($normalized, 'todo o dia')
            || str_contains($normalized, 'dia inteiro')
        ) {
            $allDay = true;
        }

        return [
            'date_text' => $dateText,
            'start_time_text' => $times[0] ?? null,
            'end_time_text' => $times[1] ?? null,
            'all_day' => $allDay,
        ];
    }

    /**
     * @return array{hour:int,minute:int}|null
     */
    private function inferPeriodTime(string $normalizedText): ?array
    {
        return match (true) {
            str_contains($normalizedText, 'manha cedo') => ['hour' => 8, 'minute' => 0],
            str_contains($normalizedText, 'inicio da manha') => ['hour' => 9, 'minute' => 0],
            str_contains($normalizedText, 'depois do almoco') => ['hour' => 14, 'minute' => 0],
            str_contains($normalizedText, 'depois de almoco') => ['hour' => 14, 'minute' => 0],
            str_contains($normalizedText, 'hora de almoco') => ['hour' => 13, 'minute' => 0],
            str_contains($normalizedText, 'antes do almoco') => ['hour' => 11, 'minute' => 0],
            str_contains($normalizedText, 'de manha') => ['hour' => 9, 'minute' => 0],
            str_contains($normalizedText, 'meio da manha') => ['hour' => 10, 'minute' => 30],
            str_contains($normalizedText, 'fim da manha') => ['hour' => 11, 'minute' => 30],
            str_contains($normalizedText, 'meio dia') => ['hour' => 12, 'minute' => 0],
            str_contains($normalizedText, 'inicio da tarde') => ['hour' => 14, 'minute' => 0],
            str_contains($normalizedText, 'de tarde') => ['hour' => 15, 'minute' => 0],
            str_contains($normalizedText, 'meio da tarde') => ['hour' => 16, 'minute' => 0],
            str_contains($normalizedText, 'fim da tarde') => ['hour' => 18, 'minute' => 0],
            str_contains($normalizedText, 'final do dia') => ['hour' => 18, 'minute' => 30],
            str_contains($normalizedText, 'fim do dia') => ['hour' => 18, 'minute' => 30],
            str_contains($normalizedText, 'a noite') => ['hour' => 20, 'minute' => 0],
            default => null,
        };
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
     * @return array{ok:bool,message:string,model:Customer|null}
     */
    private function resolveUniqueCustomer(int $companyId, ?string $term): array
    {
        if ($term === null) {
            return ['ok' => true, 'message' => '', 'model' => null];
        }

        $matches = Customer::query()
            ->forCompany($companyId)
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('nif', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->orderBy('name')
            ->limit(6)
            ->get();

        if ($matches->count() === 0) {
            return ['ok' => false, 'message' => "Nao encontrei cliente para: {$term}", 'model' => null];
        }

        if ($matches->count() > 1) {
            return ['ok' => false, 'message' => "Encontrei varios clientes para: {$term}. Refine o nome.", 'model' => null];
        }

        return ['ok' => true, 'message' => '', 'model' => $matches->first()];
    }

    /**
     * @return array{ok:bool,message:string,model:Supplier|null}
     */
    private function resolveUniqueSupplier(int $companyId, ?string $term): array
    {
        if ($term === null) {
            return ['ok' => true, 'message' => '', 'model' => null];
        }

        $matches = Supplier::query()
            ->forCompany($companyId)
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('nif', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('mobile', 'like', $like);
            })
            ->orderBy('name')
            ->limit(6)
            ->get();

        if ($matches->count() === 0) {
            return ['ok' => false, 'message' => "Nao encontrei fornecedor para: {$term}", 'model' => null];
        }

        if ($matches->count() > 1) {
            return ['ok' => false, 'message' => "Encontrei varios fornecedores para: {$term}. Refine o nome.", 'model' => null];
        }

        return ['ok' => true, 'message' => '', 'model' => $matches->first()];
    }

    /**
     * @return array{ok:bool,message:string,model:ConstructionSite|null}
     */
    private function resolveUniqueConstructionSite(int $companyId, ?string $term): array
    {
        if ($term === null) {
            return ['ok' => true, 'message' => '', 'model' => null];
        }

        $matches = ConstructionSite::query()
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
            ->orderBy('code')
            ->limit(6)
            ->get();

        if ($matches->count() === 0) {
            return ['ok' => false, 'message' => "Nao encontrei obra para: {$term}", 'model' => null];
        }

        if ($matches->count() > 1) {
            return ['ok' => false, 'message' => "Encontrei varias obras para: {$term}. Refine o termo.", 'model' => null];
        }

        return ['ok' => true, 'message' => '', 'model' => $matches->first()];
    }

    /**
     * @return array{ok:bool,message:string,model:User|null}
     */
    private function resolveUniqueResponsibleUser(int $companyId, ?string $term): array
    {
        if ($term === null) {
            return ['ok' => true, 'message' => '', 'model' => null];
        }

        $matches = User::query()
            ->where('is_super_admin', false)
            ->where('is_active', true)
            ->where('company_id', $companyId)
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like);
            })
            ->orderBy('name')
            ->limit(6)
            ->get();

        if ($matches->count() === 0) {
            return ['ok' => false, 'message' => "Nao encontrei utilizador para: {$term}", 'model' => null];
        }

        if ($matches->count() > 1) {
            return ['ok' => false, 'message' => "Encontrei varios utilizadores para: {$term}. Refine o nome.", 'model' => null];
        }

        return ['ok' => true, 'message' => '', 'model' => $matches->first()];
    }

    private function normalizeType(?string $value): string
    {
        $normalized = Str::of($value ?? '')->lower()->ascii()->squish()->value();

        return match (true) {
            str_contains($normalized, 'reun') => CalendarEvent::TYPE_MEETING,
            str_contains($normalized, 'visit') => CalendarEvent::TYPE_VISIT,
            str_contains($normalized, 'obra') => CalendarEvent::TYPE_CONSTRUCTION_SITE,
            str_contains($normalized, 'lembrete') => CalendarEvent::TYPE_REMINDER,
            str_contains($normalized, 'tarefa') => CalendarEvent::TYPE_TASK,
            default => in_array($normalized, CalendarEvent::types(), true) ? $normalized : CalendarEvent::TYPE_TASK,
        };
    }

    private function normalizePriority(?string $value): string
    {
        $normalized = Str::of($value ?? '')->lower()->ascii()->squish()->value();

        return match (true) {
            str_contains($normalized, 'alta') => CalendarEvent::PRIORITY_HIGH,
            str_contains($normalized, 'baixa') => CalendarEvent::PRIORITY_LOW,
            default => CalendarEvent::PRIORITY_NORMAL,
        };
    }

    private function buildTitle(
        ?string $title,
        ?string $requestedTitle,
        string $type,
        ?ConstructionSite $site,
        ?Customer $customer,
        ?Supplier $supplier
    ): string {
        $normalized = $this->normalizeNullableString($title);
        if ($normalized !== null) {
            return Str::limit($normalized, 190, '');
        }

        $normalizedRequested = $this->normalizeNullableString($requestedTitle);
        if ($normalizedRequested !== null) {
            return Str::limit($normalizedRequested, 190, '');
        }

        $base = CalendarEvent::typeLabels()[$type] ?? 'Evento';
        $context = $site?->name ?? $customer?->name ?? $supplier?->name;

        if (is_string($context) && trim($context) !== '') {
            return Str::limit($base.' - '.trim($context), 190, '');
        }

        return Str::limit($base.' via Telegram', 190, '');
    }

    private function extractRequestedTitleFromOriginalMessage(?string $originalMessage): ?string
    {
        if (! is_string($originalMessage) || trim($originalMessage) === '') {
            return null;
        }

        $text = trim($originalMessage);
        $text = preg_replace('/^\/agenda\b/iu', '', $text) ?? $text;
        $text = preg_replace('/^(agendar|agenda|marca|marcar|lembra-me|lembrete|cria tarefa|criar tarefa)\b/iu', '', $text) ?? $text;

        $dateTokens = '/\b(hoje|amanha|pr[oó]xima(?:\s+semana)?|semana\s+que\s+vem|segunda(?:\s+feira)?|terca(?:\s+feira)?|quarta(?:\s+feira)?|quinta(?:\s+feira)?|sexta(?:\s+feira)?|sabado|domingo|dia\s+\d{1,2}(?:[\/\-]\d{1,2}(?:[\/\-]\d{2,4})?)?|daqui\s+a\s+\d{1,2}\s+dias?|daqui\s+a\s+\d{1,2}\s+semanas?)\b/iu';
        $text = preg_replace($dateTokens, ' ', $text) ?? $text;

        $timeRanges = '/\b(das?|as)\s*\d{1,2}(?:[:h]\d{1,2})?\s*(?:as|a|-)\s*\d{1,2}(?:[:h]\d{1,2})?\b/iu';
        $text = preg_replace($timeRanges, ' ', $text) ?? $text;
        $singleTime = '/\b(as|a)\s*\d{1,2}(?:[:h]\d{1,2})?(?:\s*e\s*meia)?\b/iu';
        $text = preg_replace($singleTime, ' ', $text) ?? $text;

        $periodHints = '/\b(depois\s+do\s+almoco|depois\s+de\s+almoco|fim\s+da\s+tarde|fim\s+do\s+dia|dia\s+todo|todo\s+o\s+dia|dia\s+inteiro)\b/iu';
        $text = preg_replace($periodHints, ' ', $text) ?? $text;

        $text = $this->cleanRequestedTitleFragments($text);

        if ($text === '') {
            return null;
        }

        return Str::ucfirst($text);
    }

    private function cleanRequestedTitleFragments(string $text): string
    {
        $clean = Str::of($text)->ascii()->squish()->value();
        if ($clean === '') {
            return '';
        }

        // Remove context tails that usually pollute the title.
        $clean = preg_replace('/\b(?:na|no)\s+obra\b.*$/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\b(?:no|na)\s+cliente\b.*$/iu', '', $clean) ?? $clean;
        $clean = preg_replace('/\bcom\s+fornecedor\b.*$/iu', '', $clean) ?? $clean;

        // Remove loose connectors and prepositions from boundaries only.
        do {
            $before = $clean;
            $clean = preg_replace('/^(?:para|na|no|de|do|da|ao|a|o|e)\b[\s,:-]*/iu', '', $clean) ?? $clean;
            $clean = preg_replace('/[\s,:-]*(?:para|na|no|de|do|da|ao|a|o|e)$/iu', '', $clean) ?? $clean;
        } while ($before !== $clean);

        $clean = preg_replace('/\bobra\b[\s,:-]*/iu', '', $clean) ?? $clean;
        $clean = trim((string) preg_replace('/\s{2,}/', ' ', $clean));
        $clean = trim($clean, " \t\n\r\0\x0B,.;:-");

        return $clean;
    }

    private function buildPreviewMessage(
        array $payload,
        ?ConstructionSite $site,
        ?Customer $customer,
        ?Supplier $supplier,
        ?User $responsible
    ): string {
        $lines = [
            'Vou criar este evento:',
            '',
            'Titulo: '.(string) ($payload['title'] ?? '-'),
            'Tipo: '.mb_strtolower((string) (CalendarEvent::typeLabels()[$payload['type'] ?? ''] ?? 'tarefa'), 'UTF-8'),
            'Data/hora: '.(
                isset($payload['starts_at'])
                    ? (
                        (bool) ($payload['all_day'] ?? false)
                            ? CarbonImmutable::parse((string) $payload['starts_at'])->format('d/m/Y').' (todo o dia)'
                            : CarbonImmutable::parse((string) $payload['starts_at'])->format('d/m/Y H:i')
                    )
                    : '-'
            ),
        ];

        if ($site) {
            $lines[] = 'Obra: '.trim((string) $site->code.' - '.(string) $site->name);
        }

        if ($customer) {
            $lines[] = 'Cliente: '.(string) $customer->name;
        }

        if ($supplier) {
            $lines[] = 'Fornecedor: '.(string) $supplier->name;
        }

        if ($responsible) {
            $lines[] = 'Responsavel: '.(string) $responsible->name;
        }

        $lines[] = 'Prioridade: '.mb_strtolower((string) (CalendarEvent::priorityLabels()[$payload['priority'] ?? ''] ?? 'normal'), 'UTF-8');
        $lines[] = '';
        $lines[] = 'Responder:';
        $lines[] = 'OK CRIAR';
        $lines[] = 'CANCELAR';

        return implode("\n", $lines);
    }
}
