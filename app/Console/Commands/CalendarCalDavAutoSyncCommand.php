<?php

namespace App\Console\Commands;

use App\Models\CompanyCalendarIntegration;
use App\Services\Calendar\CalendarEventService;
use Illuminate\Console\Command;

class CalendarCalDavAutoSyncCommand extends Command
{
    protected $signature = 'calendar:caldav-auto-sync {--company_id= : Optional company id to sync}';

    protected $description = 'Auto-sync ERP calendar events to active CalDAV company integrations.';

    public function __construct(
        private readonly CalendarEventService $calendarEventService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $companyIdOption = $this->option('company_id');
        $companyId = is_numeric((string) $companyIdOption) ? (int) $companyIdOption : null;

        $query = CompanyCalendarIntegration::query()
            ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
            ->where('is_active', true)
            ->where('sync_enabled', true)
            ->whereNull('user_id')
            ->select('company_id')
            ->distinct();

        if ($companyId !== null && $companyId > 0) {
            $query->where('company_id', $companyId);
        }

        $companyIds = $query->pluck('company_id')->map(static fn ($id) => (int) $id)->all();
        if ($companyIds === []) {
            $this->info('No active CalDAV company integrations found.');

            return self::SUCCESS;
        }

        foreach ($companyIds as $id) {
            $result = $this->calendarEventService->autoSyncCompanyCalendarIfDue($id);
            $this->line(sprintf('[company:%d] %s', $id, $result['message']));
        }

        return self::SUCCESS;
    }
}

