<div class="modal fade" id="calendarEventModal" tabindex="-1" aria-labelledby="calendarEventModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calendarEventModalLabel">Nova tarefa/evento</h5>
                <button type="button" class="btn p-1" data-bs-dismiss="modal" aria-label="Close">
                    <span class="fas fa-times fs-9"></span>
                </button>
            </div>
            <form id="calendarEventForm" novalidate>
                <div class="modal-body">
                    <input type="hidden" id="calendarEventId" name="event_id" value="">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="calendarTitle" class="form-label">Titulo</label>
                            <input type="text" class="form-control" id="calendarTitle" name="title" maxlength="190" required>
                        </div>
                        <div class="col-12">
                            <label for="calendarDescription" class="form-label">Descricao</label>
                            <textarea class="form-control" id="calendarDescription" name="description" rows="3" maxlength="5000"></textarea>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="calendarType" class="form-label">Tipo</label>
                            <select class="form-select" id="calendarType" name="type" required>
                                @foreach ($eventTypeLabels as $typeValue => $typeLabel)
                                    <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="calendarStatus" class="form-label">Estado</label>
                            <select class="form-select" id="calendarStatus" name="status" required>
                                @foreach ($eventStatusLabels as $statusValue => $statusLabel)
                                    <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="calendarPriority" class="form-label">Prioridade</label>
                            <select class="form-select" id="calendarPriority" name="priority" required>
                                @foreach ($eventPriorityLabels as $priorityValue => $priorityLabel)
                                    <option value="{{ $priorityValue }}">{{ $priorityLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-6 col-md-3">
                            <div class="form-check mt-md-4 pt-md-2">
                                <input class="form-check-input" type="checkbox" value="1" id="calendarAllDay" name="all_day">
                                <label class="form-check-label" for="calendarAllDay">
                                    Dia inteiro
                                </label>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="calendarStartsAt" class="form-label">Inicio</label>
                            <input type="datetime-local" class="form-control" id="calendarStartsAt" name="starts_at" required>
                        </div>
                        <div class="col-12 col-md-5">
                            <label for="calendarEndsAt" class="form-label">Fim</label>
                            <input type="datetime-local" class="form-control" id="calendarEndsAt" name="ends_at">
                        </div>

                        <div class="col-12 col-md-6">
                            <label for="calendarUserId" class="form-label">Responsavel</label>
                            <select class="form-select" id="calendarUserId" name="user_id">
                                <option value="">Sem responsavel</option>
                                @foreach ($responsibleUsers as $responsibleUser)
                                    <option value="{{ $responsibleUser->id }}">{{ $responsibleUser->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="calendarCustomerId" class="form-label">Cliente</label>
                            <select class="form-select" id="calendarCustomerId" name="customer_id">
                                <option value="">Sem cliente</option>
                                @foreach ($customers as $customer)
                                    <option value="{{ $customer->id }}">{{ $customer->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="calendarSupplierId" class="form-label">Fornecedor</label>
                            <select class="form-select" id="calendarSupplierId" name="supplier_id">
                                <option value="">Sem fornecedor</option>
                                @foreach ($suppliers as $supplier)
                                    <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="calendarConstructionSiteId" class="form-label">Obra</label>
                            <select class="form-select" id="calendarConstructionSiteId" name="construction_site_id">
                                <option value="">Sem obra</option>
                                @foreach ($constructionSites as $constructionSite)
                                    <option value="{{ $constructionSite->id }}">{{ $constructionSite->code }} - {{ $constructionSite->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="calendarQuoteId" class="form-label">Orcamento</label>
                            <select class="form-select" id="calendarQuoteId" name="quote_id">
                                <option value="">Sem orcamento</option>
                                @foreach ($quotes as $quote)
                                    <option value="{{ $quote->id }}">{{ $quote->number }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-phoenix-danger d-none" id="calendarDeleteBtn">Eliminar</button>
                        <button type="button" class="btn btn-phoenix-secondary d-none" id="calendarCancelBtn">Cancelar tarefa</button>
                        <button type="button" class="btn btn-phoenix-success d-none" id="calendarCompleteBtn">Concluir tarefa</button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-phoenix-secondary" data-bs-dismiss="modal">Fechar</button>
                        <button type="submit" class="btn btn-primary" id="calendarSaveBtn">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

