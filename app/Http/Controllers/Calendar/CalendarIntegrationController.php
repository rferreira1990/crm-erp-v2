<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\UpdateCompanyCalendarIntegrationRequest;
use App\Models\CompanyCalendarIntegration;
use App\Models\User;
use App\Services\Calendar\CalDavCalendarSyncService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class CalendarIntegrationController extends Controller
{
    public function __construct(
        private readonly CalDavCalendarSyncService $calDavCalendarSyncService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CompanyCalendarIntegration::class);

        $companyId = (int) $request->user()->company_id;

        return view('calendar.integrations', [
            'integration' => CompanyCalendarIntegration::query()
                ->forCompany($companyId)
                ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
                ->whereNull('user_id')
                ->latest('id')
                ->first(),
            'activeUsers' => User::query()
                ->where('is_super_admin', false)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function update(UpdateCompanyCalendarIntegrationRequest $request): RedirectResponse
    {
        $this->authorize('create', CompanyCalendarIntegration::class);

        $companyId = (int) $request->user()->company_id;
        $data = $request->validated();
        $selectedUserId = isset($data['user_id']) ? (int) $data['user_id'] : null;

        if ($selectedUserId !== null && $selectedUserId > 0) {
            User::query()
                ->where('is_super_admin', false)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->whereKey($selectedUserId)
                ->firstOrFail();
        } else {
            $selectedUserId = null;
        }

        $integration = CompanyCalendarIntegration::query()
            ->forCompany($companyId)
            ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
            ->where('user_id', $selectedUserId)
            ->latest('id')
            ->first();

        if (! $integration) {
            $integration = new CompanyCalendarIntegration([
                'company_id' => $companyId,
                'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
                'user_id' => $selectedUserId,
            ]);
        }

        $payload = [
            'name' => $data['name'],
            'username' => $data['username'],
            'base_url' => $data['base_url'],
            'calendar_url' => $data['calendar_url'],
            'is_active' => (bool) $data['is_active'],
            'sync_enabled' => (bool) $data['sync_enabled'],
            'provider' => CompanyCalendarIntegration::PROVIDER_CALDAV,
            'user_id' => $selectedUserId,
        ];

        if (is_string($data['password'] ?? null) && trim((string) $data['password']) !== '') {
            $payload['password'] = $data['password'];
        }

        $integration->forceFill($payload)->save();

        return redirect()
            ->route('admin.calendar.integrations.index')
            ->with('status', 'Integracao CalDAV atualizada com sucesso.');
    }

    public function testConnection(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', CompanyCalendarIntegration::class);

        $companyId = (int) $request->user()->company_id;
        $integration = $this->findCompanyMainIntegrationOrFail($companyId);

        $result = $this->calDavCalendarSyncService->testConnection($integration);

        if (! $result['ok']) {
            return redirect()
                ->route('admin.calendar.integrations.index')
                ->withErrors(['integration_test' => $result['message']]);
        }

        return redirect()
            ->route('admin.calendar.integrations.index')
            ->with('status', $result['message']);
    }

    public function syncNow(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', CompanyCalendarIntegration::class);

        $companyId = (int) $request->user()->company_id;
        $integration = $this->findCompanyMainIntegrationOrFail($companyId);

        try {
            $result = $this->calDavCalendarSyncService->syncCompanyEvents($integration, $companyId);
        } catch (Throwable $exception) {
            Log::warning('Calendar CalDAV sync now failed', [
                'company_id' => $companyId,
                'integration_id' => (int) $integration->id,
                'error' => $exception->getMessage(),
            ]);

            return redirect()
                ->route('admin.calendar.integrations.index')
                ->withErrors(['integration_sync' => 'Falha ao sincronizar agenda com CalDAV.']);
        }

        return redirect()
            ->route('admin.calendar.integrations.index')
            ->with('status', sprintf(
                'Sincronizacao concluida. %d sincronizado(s), %d com falha.',
                $result['synced'],
                $result['failed']
            ));
    }

    private function findCompanyMainIntegrationOrFail(int $companyId): CompanyCalendarIntegration
    {
        return CompanyCalendarIntegration::query()
            ->forCompany($companyId)
            ->where('provider', CompanyCalendarIntegration::PROVIDER_CALDAV)
            ->whereNull('user_id')
            ->where('is_active', true)
            ->latest('id')
            ->firstOrFail();
    }
}

