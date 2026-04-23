@php
    $workDateValue = old('work_date', isset($entry) ? optional($entry->work_date)->format('Y-m-d') : now()->toDateString());
    $selectedUserId = (int) old('user_id', isset($entry) ? $entry->user_id : 0);
    $hoursValue = old('hours', isset($entry) ? number_format((float) $entry->hours, 2, '.', '') : '1.00');
    $descriptionValue = old('description', isset($entry) ? $entry->description : null);
    $taskTypeValue = old('task_type', isset($entry) ? $entry->task_type : null);
    $workerLookup = $workerOptions->keyBy('id');
    $selectedWorker = $selectedUserId > 0 ? $workerLookup->get($selectedUserId) : null;
    $initialHourlyCost = $selectedWorker?->resolved_hourly_cost ?? (isset($entry) ? (float) $entry->hourly_cost : 0.0);
@endphp

<form method="POST" action="{{ $formAction }}" class="row g-4">
    @csrf
    @if (strtoupper($formMethod) === 'PATCH')
        @method('PATCH')
    @endif

    <div class="col-12">
        <div class="card">
            <div class="card-header bg-body-tertiary">
                <h5 class="mb-0">Dados do lancamento</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="work_date">Data de trabalho</label>
                        <input type="date" id="work_date" name="work_date" value="{{ $workDateValue }}" class="form-control @error('work_date') is-invalid @enderror" required>
                        @error('work_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="user_id">Colaborador</label>
                        <select id="user_id" name="user_id" class="form-select @error('user_id') is-invalid @enderror" required>
                            <option value="">Selecionar colaborador</option>
                            @foreach ($workerOptions as $worker)
                                <option
                                    value="{{ $worker->id }}"
                                    data-hourly-cost="{{ number_format((float) ($worker->resolved_hourly_cost ?? 0), 4, '.', '') }}"
                                    @selected($selectedUserId === (int) $worker->id)
                                >
                                    {{ $worker->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('user_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="hours">Horas</label>
                        <input type="number" id="hours" name="hours" value="{{ $hoursValue }}" min="1" max="24" step="0.25" class="form-control @error('hours') is-invalid @enderror" required>
                        @error('hours')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="hourly_cost_preview">Custo/hora (snapshot)</label>
                        <input type="text" id="hourly_cost_preview" class="form-control" value="{{ number_format((float) $initialHourlyCost, 2, ',', '.') }} EUR" readonly>
                        <div class="form-text">
                            O custo/hora vem do colaborador em
                            <a href="{{ route('admin.users.index') }}">Admin > Utilizadores</a>.
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="total_cost_preview">Custo total estimado</label>
                        <input type="text" id="total_cost_preview" class="form-control" value="{{ number_format((float) ($initialHourlyCost * (float) $hoursValue), 2, ',', '.') }} EUR" readonly>
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="task_type">Tipo de tarefa</label>
                        <select id="task_type" name="task_type" class="form-select @error('task_type') is-invalid @enderror">
                            <option value="">Sem tipo</option>
                            @foreach ($taskTypeOptions as $taskType => $taskTypeLabel)
                                <option value="{{ $taskType }}" @selected($taskTypeValue === $taskType)>{{ $taskTypeLabel }}</option>
                            @endforeach
                        </select>
                        @error('task_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="description">Descricao do trabalho</label>
                        <textarea id="description" name="description" rows="3" class="form-control @error('description') is-invalid @enderror" required maxlength="255">{{ $descriptionValue }}</textarea>
                        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-12 d-flex justify-content-end gap-2">
        <a href="{{ $cancelUrl }}" class="btn btn-phoenix-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
    </div>
</form>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const workerSelect = document.getElementById('user_id');
            const hoursInput = document.getElementById('hours');
            const hourlyCostPreview = document.getElementById('hourly_cost_preview');
            const totalCostPreview = document.getElementById('total_cost_preview');

            if (!workerSelect || !hoursInput || !hourlyCostPreview || !totalCostPreview) {
                return;
            }

            function parseNumber(value) {
                const parsed = Number.parseFloat(value);
                return Number.isFinite(parsed) ? parsed : 0;
            }

            function formatCurrency(value) {
                return value.toLocaleString('pt-PT', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2,
                }) + ' EUR';
            }

            function updateCostPreview() {
                const selectedOption = workerSelect.options[workerSelect.selectedIndex];
                const hourlyCost = parseNumber(selectedOption ? selectedOption.getAttribute('data-hourly-cost') : null);
                const hours = parseNumber(hoursInput.value);
                const total = hourlyCost * hours;

                hourlyCostPreview.value = formatCurrency(hourlyCost);
                totalCostPreview.value = formatCurrency(total);
            }

            workerSelect.addEventListener('change', updateCostPreview);
            hoursInput.addEventListener('input', updateCostPreview);
            updateCostPreview();
        });
    </script>
@endpush
