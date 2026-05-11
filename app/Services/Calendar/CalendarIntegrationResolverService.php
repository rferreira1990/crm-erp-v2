<?php

namespace App\Services\Calendar;

use App\Models\CalendarEvent;
use App\Models\CompanyCalendarIntegration;

class CalendarIntegrationResolverService
{
    public function resolveCompanyMainIntegration(int $companyId): ?CompanyCalendarIntegration
    {
        return CompanyCalendarIntegration::query()
            ->forCompany($companyId)
            ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->whereNull('user_id')
            ->orderByDesc('id')
            ->first();
    }

    public function resolveForEvent(CalendarEvent $event): ?CompanyCalendarIntegration
    {
        $companyId = (int) $event->company_id;
        $eventUserId = $event->user_id ? (int) $event->user_id : null;

        if ($eventUserId !== null) {
            $userIntegration = CompanyCalendarIntegration::query()
                ->forCompany($companyId)
                ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
                ->where('is_active', true)
                ->where('sync_enabled', true)
                ->where('user_id', $eventUserId)
                ->orderByDesc('id')
                ->first();

            if ($userIntegration) {
                return $userIntegration;
            }
        }

        return $this->resolveCompanyMainIntegration($companyId);
    }
}
