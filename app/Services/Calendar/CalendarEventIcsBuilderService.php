<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class CalendarEventIcsBuilderService
{
    public function build(CalendarEvent $event): string
    {
        $uid = $this->eventUid($event);
        $timezone = (string) config('app.timezone', 'UTC');
        $nowUtc = Carbon::now('UTC');

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//CRM ERP//Calendar//PT',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:'.$uid,
            'DTSTAMP:'.$nowUtc->format('Ymd\THis\Z'),
            'CREATED:'.$this->asUtc($event->created_at ?? $nowUtc),
            'LAST-MODIFIED:'.$this->asUtc($event->updated_at ?? $nowUtc),
            'SUMMARY:'.$this->escapeText((string) $event->title),
        ];

        $description = trim((string) ($event->description ?? ''));
        if ($description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escapeText($description);
        }

        if ($event->all_day) {
            $startDate = ($event->starts_at ?? now())->copy()->startOfDay();
            $endDate = $event->ends_at
                ? $event->ends_at->copy()->startOfDay()->addDay()
                : $startDate->copy()->addDay();

            $lines[] = 'DTSTART;VALUE=DATE:'.$startDate->format('Ymd');
            $lines[] = 'DTEND;VALUE=DATE:'.$endDate->format('Ymd');
        } else {
            $startsAt = $event->starts_at ?? now();
            $endsAt = $event->ends_at ?? $startsAt->copy()->addHour();

            $lines[] = 'DTSTART;TZID='.$timezone.':'.$startsAt->format('Ymd\THis');
            $lines[] = 'DTEND;TZID='.$timezone.':'.$endsAt->format('Ymd\THis');
        }

        $lines[] = 'STATUS:'.$this->eventStatus($event);
        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    public function eventUid(CalendarEvent $event): string
    {
        return sprintf(
            'crm-erp-company-%d-event-%d@crm-erp',
            (int) $event->company_id,
            (int) $event->id
        );
    }

    private function eventStatus(CalendarEvent $event): string
    {
        return $event->status === CalendarEvent::STATUS_CANCELLED ? 'CANCELLED' : 'CONFIRMED';
    }

    private function asUtc(Carbon $dateTime): string
    {
        return $dateTime->copy()->setTimezone('UTC')->format('Ymd\THis\Z');
    }

    private function escapeText(string $value): string
    {
        return Str::of($value)
            ->replace('\\', '\\\\')
            ->replace(';', '\;')
            ->replace(',', '\,')
            ->replace("\r\n", '\n')
            ->replace("\n", '\n')
            ->value();
    }
}

