@extends('layouts.admin')

@section('title', 'Agenda')
@section('page_title', 'Agenda e Tarefas')
@section('page_subtitle', 'Planeamento interno da empresa')

@section('breadcrumbs')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
        <li class="breadcrumb-item active" aria-current="page">Agenda</li>
    </ol>
@endsection

@section('page_actions')
    @if ($canManageIntegrations)
        <a href="{{ route('admin.calendar.integrations.index') }}" class="btn btn-phoenix-secondary me-2">
            <span class="fas fa-cloud me-1"></span>CalDAV
        </a>
    @endif
    @if ($canCreate)
        <button type="button" class="btn btn-primary" id="calendarNewEventBtn">
            <span class="fas fa-plus me-1"></span>Nova tarefa
        </button>
    @endif
@endsection

@section('content')
    <div class="row g-3">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="row g-2 align-items-end">
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1" for="calendarFilterType">Tipo</label>
                            <select class="form-select" id="calendarFilterType">
                                <option value="">Todos</option>
                                @foreach ($eventTypeLabels as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1" for="calendarFilterStatus">Estado</label>
                            <select class="form-select" id="calendarFilterStatus">
                                <option value="">Todos</option>
                                @foreach ($eventStatusLabels as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1" for="calendarFilterUser">Responsavel</label>
                            <select class="form-select" id="calendarFilterUser">
                                <option value="">Todos</option>
                                @foreach ($responsibleUsers as $responsibleUser)
                                    <option value="{{ $responsibleUser->id }}">{{ $responsibleUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label mb-1" for="calendarFilterCustomer">Cliente</label>
                            <select class="form-select" id="calendarFilterCustomer">
                                <option value="">Todos</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label mb-1" for="calendarFilterConstructionSite">Obra</label>
                            <select class="form-select" id="calendarFilterConstructionSite">
                                <option value="">Todas</option>
                                @foreach ($constructionSites as $constructionSite)
                                    <option value="{{ $constructionSite->id }}">{{ $constructionSite->code }} - {{ $constructionSite->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <div id="calendarFeedback" class="alert d-none" role="alert"></div>
                    @if(($calendarSync['enabled'] ?? false) === true)
                        <div class="alert alert-soft-info mb-3" role="alert">
                            <span class="me-1 fas fa-cloud"></span>
                            {{ $calendarSync['message'] ?? 'Sincronizacao CalDAV ativa.' }}
                        </div>
                    @endif
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        @foreach ($responsibleLegend as $legend)
                            <span class="badge badge-phoenix fs-10 px-2 py-1 border" style="background-color: {{ $legend['color'] }}; color: {{ $legend['text_color'] }};">
                                {{ $legend['name'] }}
                            </span>
                        @endforeach
                    </div>
                    <div id="erpCalendar"></div>
                </div>
            </div>
        </div>

        <div class="col-12 d-lg-none">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">Hoje</h5>
                    <small class="text-body-secondary" id="calendarTodayDateLabel"></small>
                </div>
                <div class="card-body" id="calendarTodayList">
                    <p class="text-body-secondary mb-0">Sem eventos para hoje.</p>
                </div>
            </div>
        </div>
    </div>

    @include('calendar.partials.modal')
@endsection

@push('styles')
    <style>
        #erpCalendar .fc .fc-toolbar-title {
            font-size: 1rem;
            font-weight: 700;
        }

        #erpCalendar .fc .fc-button {
            text-transform: capitalize;
        }

        #erpCalendar .fc-event {
            cursor: pointer;
            border-radius: 0.375rem;
            padding: 0.1rem 0.35rem;
            font-size: 0.75rem;
        }

        #erpCalendar .fc-daygrid-event-harness {
            margin-top: 0.2rem;
        }
    </style>
@endpush

@push('scripts')
    <script src="{{ asset('vendor/phoenix/vendors/fullcalendar/index.global.min.js') }}"></script>
    <script>
        (() => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
            const canCreate = @json($canCreate);
            const canUpdate = @json($canUpdate);
            const canDelete = @json($canDelete);
            const autoRefreshMs = 5 * 60 * 1000;

            const routes = {
                events: @json(route('admin.calendar.events', absolute: false)),
                store: @json(route('admin.calendar.events.store', absolute: false)),
                updateBase: @json(route('admin.calendar.events.update', ['calendarEvent' => '__id__'], absolute: false)),
                completeBase: @json(route('admin.calendar.events.complete', ['calendarEvent' => '__id__'], absolute: false)),
                cancelBase: @json(route('admin.calendar.events.cancel', ['calendarEvent' => '__id__'], absolute: false)),
                destroyBase: @json(route('admin.calendar.events.destroy', ['calendarEvent' => '__id__'], absolute: false)),
            };

            const selectors = {
                filterType: document.getElementById('calendarFilterType'),
                filterStatus: document.getElementById('calendarFilterStatus'),
                filterUser: document.getElementById('calendarFilterUser'),
                filterCustomer: document.getElementById('calendarFilterCustomer'),
                filterConstructionSite: document.getElementById('calendarFilterConstructionSite'),
                feedback: document.getElementById('calendarFeedback'),
                newButton: document.getElementById('calendarNewEventBtn'),
                modal: document.getElementById('calendarEventModal'),
                form: document.getElementById('calendarEventForm'),
                modalTitle: document.getElementById('calendarEventModalLabel'),
                eventId: document.getElementById('calendarEventId'),
                saveButton: document.getElementById('calendarSaveBtn'),
                deleteButton: document.getElementById('calendarDeleteBtn'),
                completeButton: document.getElementById('calendarCompleteBtn'),
                cancelButton: document.getElementById('calendarCancelBtn'),
                allDay: document.getElementById('calendarAllDay'),
                startsAt: document.getElementById('calendarStartsAt'),
                endsAt: document.getElementById('calendarEndsAt'),
                todayList: document.getElementById('calendarTodayList'),
                todayDateLabel: document.getElementById('calendarTodayDateLabel'),
            };

            const getUpdateUrl = (id) => routes.updateBase.replace('__id__', String(id));
            const getCompleteUrl = (id) => routes.completeBase.replace('__id__', String(id));
            const getCancelUrl = (id) => routes.cancelBase.replace('__id__', String(id));
            const getDestroyUrl = (id) => routes.destroyBase.replace('__id__', String(id));

            const modal = selectors.modal ? new bootstrap.Modal(selectors.modal) : null;
            let calendar = null;
            let lastFetchedEvents = [];

            const showFeedback = (variant, message) => {
                if (!(selectors.feedback instanceof HTMLElement)) {
                    return;
                }

                selectors.feedback.className = `alert alert-${variant}`;
                selectors.feedback.textContent = message;
                selectors.feedback.classList.remove('d-none');

                window.setTimeout(() => {
                    selectors.feedback?.classList.add('d-none');
                }, 5000);
            };

            const resetForm = () => {
                if (!(selectors.form instanceof HTMLFormElement)) {
                    return;
                }

                selectors.form.reset();
                selectors.eventId.value = '';
                selectors.modalTitle.textContent = 'Nova tarefa/evento';
                selectors.saveButton.textContent = 'Guardar';

                selectors.deleteButton.classList.add('d-none');
                selectors.completeButton.classList.add('d-none');
                selectors.cancelButton.classList.add('d-none');
            };

            const fillForm = (event) => {
                if (!(selectors.form instanceof HTMLFormElement)) {
                    return;
                }

                const props = event.extendedProps || {};
                selectors.eventId.value = String(event.id);
                selectors.modalTitle.textContent = 'Editar tarefa/evento';
                selectors.saveButton.textContent = 'Atualizar';

                selectors.form.querySelector('[name="title"]').value = event.title ?? '';
                selectors.form.querySelector('[name="description"]').value = props.description ?? '';
                selectors.form.querySelector('[name="type"]').value = props.type ?? 'task';
                selectors.form.querySelector('[name="status"]').value = props.status ?? 'pending';
                selectors.form.querySelector('[name="priority"]').value = props.priority ?? 'normal';
                selectors.form.querySelector('[name="user_id"]').value = props.responsible_id ?? '';
                selectors.form.querySelector('[name="customer_id"]').value = props.customer_id ?? '';
                selectors.form.querySelector('[name="supplier_id"]').value = props.supplier_id ?? '';
                selectors.form.querySelector('[name="construction_site_id"]').value = props.construction_site_id ?? '';
                selectors.form.querySelector('[name="quote_id"]').value = props.quote_id ?? '';

                selectors.allDay.checked = !!event.allDay;
                selectors.startsAt.value = toDateTimeLocal(event.start);
                selectors.endsAt.value = event.end ? toDateTimeLocal(event.end) : '';

                if (canDelete) {
                    selectors.deleteButton.classList.remove('d-none');
                }

                if (canUpdate) {
                    selectors.completeButton.classList.toggle('d-none', props.status === 'completed');
                    selectors.cancelButton.classList.toggle('d-none', props.status === 'cancelled');
                }
            };

            const toDateTimeLocal = (dateValue) => {
                if (!dateValue) {
                    return '';
                }

                const date = new Date(dateValue);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }

                const pad = (value) => String(value).padStart(2, '0');
                return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
            };

            const gatherFilters = () => {
                return {
                    type: selectors.filterType?.value?.trim() || '',
                    status: selectors.filterStatus?.value?.trim() || '',
                    user_id: selectors.filterUser?.value?.trim() || '',
                    customer_id: selectors.filterCustomer?.value?.trim() || '',
                    construction_site_id: selectors.filterConstructionSite?.value?.trim() || '',
                };
            };

            const buildFormPayload = () => {
                const formData = new FormData(selectors.form);
                const payload = Object.fromEntries(formData.entries());

                payload.all_day = selectors.allDay.checked;
                payload.starts_at = payload.starts_at || null;
                payload.ends_at = payload.ends_at || null;

                ['user_id', 'customer_id', 'supplier_id', 'construction_site_id', 'quote_id'].forEach((key) => {
                    if (typeof payload[key] === 'string' && payload[key].trim() === '') {
                        payload[key] = null;
                    }
                });

                return payload;
            };

            const requestJson = async (url, method, payload = null) => {
                const options = {
                    method,
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    credentials: 'same-origin',
                };

                if (payload !== null) {
                    options.body = JSON.stringify(payload);
                }

                const response = await fetch(url, options);
                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    const validationErrors = data?.errors ? Object.values(data.errors).flat().join(' ') : '';
                    throw new Error(validationErrors || data?.message || 'Operacao invalida.');
                }

                return data;
            };

            const renderTodayList = (events) => {
                if (!(selectors.todayList instanceof HTMLElement)) {
                    return;
                }

                const today = new Date();
                const todayYear = today.getFullYear();
                const todayMonth = today.getMonth();
                const todayDate = today.getDate();

                const todaysEvents = events.filter((event) => {
                    if (!event?.start) {
                        return false;
                    }

                    const start = new Date(event.start);
                    if (Number.isNaN(start.getTime())) {
                        return false;
                    }

                    return start.getFullYear() === todayYear
                        && start.getMonth() === todayMonth
                        && start.getDate() === todayDate;
                });

                selectors.todayDateLabel.textContent = today.toLocaleDateString('pt-PT', {
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                });

                if (todaysEvents.length === 0) {
                    selectors.todayList.innerHTML = '<p class="text-body-secondary mb-0">Sem eventos para hoje.</p>';
                    return;
                }

                selectors.todayList.innerHTML = todaysEvents
                    .map((event) => {
                        const time = event.allDay
                            ? 'Dia inteiro'
                            : new Date(event.start).toLocaleTimeString('pt-PT', { hour: '2-digit', minute: '2-digit' });
                        const status = event.extendedProps?.status_label ?? '';
                        return `
                            <div class="border rounded-2 p-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start gap-2">
                                    <strong class="d-block">${event.title ?? ''}</strong>
                                    <span class="badge badge-phoenix badge-phoenix-secondary">${status}</span>
                                </div>
                                <div class="fs-9 text-body-secondary">${time}</div>
                            </div>
                        `;
                    })
                    .join('');
            };

            const initCalendar = () => {
                const calendarEl = document.getElementById('erpCalendar');
                if (!(calendarEl instanceof HTMLElement) || !window.FullCalendar) {
                    return;
                }

                calendar = new window.FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    locale: 'pt',
                    height: 'auto',
                    dayMaxEvents: 3,
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay',
                    },
                    buttonText: {
                        today: 'Hoje',
                        month: 'Mes',
                        week: 'Semana',
                        day: 'Dia',
                    },
                    events: (fetchInfo, successCallback, failureCallback) => {
                        const params = new URLSearchParams({
                            start: fetchInfo.startStr,
                            end: fetchInfo.endStr,
                            ...gatherFilters(),
                        });

                        fetch(`${routes.events}?${params.toString()}`, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json',
                            },
                            credentials: 'same-origin',
                        })
                            .then((response) => {
                                if (!response.ok) {
                                    throw new Error('Nao foi possivel carregar eventos.');
                                }
                                return response.json();
                            })
                            .then((events) => {
                                lastFetchedEvents = Array.isArray(events) ? events : [];
                                renderTodayList(lastFetchedEvents);
                                successCallback(lastFetchedEvents);
                            })
                            .catch((error) => {
                                failureCallback(error);
                                showFeedback('danger', 'Falha ao carregar agenda.');
                            });
                    },
                    dateClick: (info) => {
                        if (!canCreate || !(selectors.form instanceof HTMLFormElement)) {
                            return;
                        }

                        resetForm();
                        selectors.startsAt.value = `${info.dateStr}T09:00`;
                        selectors.endsAt.value = `${info.dateStr}T10:00`;
                        modal?.show();
                    },
                    eventClick: (info) => {
                        if (!canUpdate && !canDelete) {
                            return;
                        }

                        resetForm();
                        fillForm(info.event);
                        modal?.show();
                    },
                });

                calendar.render();
            };

            const reloadCalendar = () => {
                if (calendar) {
                    calendar.refetchEvents();
                }
            };

            selectors.newButton?.addEventListener('click', () => {
                resetForm();
                const now = new Date();
                now.setMinutes(0, 0, 0);
                const end = new Date(now.getTime() + 60 * 60 * 1000);
                selectors.startsAt.value = toDateTimeLocal(now);
                selectors.endsAt.value = toDateTimeLocal(end);
                modal?.show();
            });

            [selectors.filterType, selectors.filterStatus, selectors.filterUser, selectors.filterCustomer, selectors.filterConstructionSite].forEach((field) => {
                field?.addEventListener('change', reloadCalendar);
            });

            selectors.form?.addEventListener('submit', async (event) => {
                event.preventDefault();
                if (!(selectors.form instanceof HTMLFormElement)) {
                    return;
                }

                selectors.saveButton.disabled = true;

                try {
                    const payload = buildFormPayload();
                    const eventId = selectors.eventId.value.trim();
                    if (eventId === '') {
                        await requestJson(routes.store, 'POST', payload);
                        showFeedback('success', 'Evento criado com sucesso.');
                    } else {
                        await requestJson(getUpdateUrl(eventId), 'PUT', payload);
                        showFeedback('success', 'Evento atualizado com sucesso.');
                    }

                    modal?.hide();
                    reloadCalendar();
                } catch (error) {
                    showFeedback('danger', error instanceof Error ? error.message : 'Falha ao guardar evento.');
                } finally {
                    selectors.saveButton.disabled = false;
                }
            });

            selectors.completeButton?.addEventListener('click', async () => {
                const eventId = selectors.eventId.value.trim();
                if (eventId === '') {
                    return;
                }

                try {
                    await requestJson(getCompleteUrl(eventId), 'PATCH');
                    showFeedback('success', 'Tarefa concluida com sucesso.');
                    modal?.hide();
                    reloadCalendar();
                } catch (error) {
                    showFeedback('danger', error instanceof Error ? error.message : 'Falha ao concluir tarefa.');
                }
            });

            selectors.cancelButton?.addEventListener('click', async () => {
                const eventId = selectors.eventId.value.trim();
                if (eventId === '') {
                    return;
                }

                try {
                    await requestJson(getCancelUrl(eventId), 'PATCH');
                    showFeedback('success', 'Tarefa cancelada com sucesso.');
                    modal?.hide();
                    reloadCalendar();
                } catch (error) {
                    showFeedback('danger', error instanceof Error ? error.message : 'Falha ao cancelar tarefa.');
                }
            });

            selectors.deleteButton?.addEventListener('click', async () => {
                const eventId = selectors.eventId.value.trim();
                if (eventId === '') {
                    return;
                }

                if (!window.confirm('Eliminar este evento?')) {
                    return;
                }

                try {
                    await requestJson(getDestroyUrl(eventId), 'DELETE');
                    showFeedback('success', 'Evento removido com sucesso.');
                    modal?.hide();
                    reloadCalendar();
                } catch (error) {
                    showFeedback('danger', error instanceof Error ? error.message : 'Falha ao eliminar evento.');
                }
            });

            selectors.allDay?.addEventListener('change', () => {
                const allDay = selectors.allDay.checked;
                selectors.startsAt.type = allDay ? 'date' : 'datetime-local';
                selectors.endsAt.type = allDay ? 'date' : 'datetime-local';
            });

            initCalendar();

            window.setInterval(() => {
                reloadCalendar();
            }, autoRefreshMs);
        })();
    </script>
@endpush
