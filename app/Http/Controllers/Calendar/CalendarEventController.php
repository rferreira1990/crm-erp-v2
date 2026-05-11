<?php

namespace App\Http\Controllers\Calendar;

use App\Http\Controllers\Controller;
use App\Http\Requests\Calendar\StoreCalendarEventRequest;
use App\Http\Requests\Calendar\UpdateCalendarEventRequest;
use App\Models\CalendarEvent;
use App\Models\ConstructionSite;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Calendar\CalendarEventService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Throwable;

class CalendarEventController extends Controller
{
    public function __construct(
        private readonly CalendarEventService $calendarEventService
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $companyId = (int) $request->user()->company_id;
        $calendarSync = $this->calendarEventService->autoSyncCompanyCalendarIfDue($companyId);

        return view('calendar.index', [
            'eventTypeLabels' => CalendarEvent::typeLabels(),
            'eventStatusLabels' => CalendarEvent::statusLabels(),
            'eventPriorityLabels' => CalendarEvent::priorityLabels(),
            'responsibleUsers' => User::query()
                ->where('is_super_admin', false)
                ->where('company_id', $companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'customers' => Customer::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'suppliers' => Supplier::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'constructionSites' => ConstructionSite::query()
                ->forCompany($companyId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'name']),
            'quotes' => Quote::query()
                ->forCompany($companyId)
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->limit(300)
                ->get(['id', 'number']),
            'canCreate' => $request->user()->can('company.calendar.create'),
            'canUpdate' => $request->user()->can('company.calendar.update'),
            'canDelete' => $request->user()->can('company.calendar.delete'),
            'canManageIntegrations' => $request->user()->can('company.calendar.integrations.manage'),
            'responsibleLegend' => $this->calendarEventService->buildResponsibleLegend($companyId),
            'calendarSync' => $calendarSync,
        ]);
    }

    public function events(Request $request): JsonResponse
    {
        $this->authorize('viewAny', CalendarEvent::class);

        $companyId = (int) $request->user()->company_id;
        $this->calendarEventService->autoSyncCompanyCalendarIfDue($companyId);
        $filters = Arr::only($request->query(), [
            'start',
            'end',
            'type',
            'status',
            'user_id',
            'customer_id',
            'construction_site_id',
        ]);

        $events = $this->calendarEventService->eventsForCalendar($companyId, $filters);

        return response()->json($events);
    }

    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        $this->authorize('create', CalendarEvent::class);

        $companyId = (int) $request->user()->company_id;
        $actorUserId = (int) $request->user()->id;

        try {
            $calendarEvent = $this->calendarEventService->create(
                companyId: $companyId,
                actorUserId: $actorUserId,
                payload: $request->validated()
            );
        } catch (Throwable $exception) {
            if ($this->shouldAbort404($exception)) {
                abort(404);
            }

            throw $exception;
        }

        $this->authorize('view', $calendarEvent);

        return response()->json([
            'message' => 'Evento criado com sucesso.',
            'id' => (int) $calendarEvent->id,
        ], 201);
    }

    public function update(UpdateCalendarEventRequest $request, int $calendarEvent): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $existing = $this->findCompanyEventOrFail($companyId, $calendarEvent);
        $this->authorize('update', $existing);

        try {
            $updated = $this->calendarEventService->update(
                companyId: $companyId,
                calendarEventId: (int) $existing->id,
                payload: $request->validated()
            );
        } catch (Throwable $exception) {
            if ($this->shouldAbort404($exception)) {
                abort(404);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Evento atualizado com sucesso.',
            'id' => (int) $updated->id,
        ]);
    }

    public function complete(Request $request, int $calendarEvent): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $existing = $this->findCompanyEventOrFail($companyId, $calendarEvent);
        $this->authorize('update', $existing);

        $updated = $this->calendarEventService->complete($companyId, (int) $existing->id);

        return response()->json([
            'message' => 'Tarefa concluida com sucesso.',
            'id' => (int) $updated->id,
        ]);
    }

    public function cancel(Request $request, int $calendarEvent): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $existing = $this->findCompanyEventOrFail($companyId, $calendarEvent);
        $this->authorize('update', $existing);

        $updated = $this->calendarEventService->cancel($companyId, (int) $existing->id);

        return response()->json([
            'message' => 'Tarefa cancelada com sucesso.',
            'id' => (int) $updated->id,
        ]);
    }

    public function destroy(Request $request, int $calendarEvent): JsonResponse
    {
        $companyId = (int) $request->user()->company_id;
        $existing = $this->findCompanyEventOrFail($companyId, $calendarEvent);
        $this->authorize('delete', $existing);

        $this->calendarEventService->delete($companyId, (int) $existing->id);

        return response()->json([
            'message' => 'Evento removido com sucesso.',
        ]);
    }

    private function findCompanyEventOrFail(int $companyId, int $calendarEventId): CalendarEvent
    {
        return CalendarEvent::query()
            ->forCompany($companyId)
            ->whereKey($calendarEventId)
            ->firstOrFail();
    }

    private function shouldAbort404(Throwable $exception): bool
    {
        return $exception instanceof ModelNotFoundException;
    }
}
