<div class="card">
    <div class="card-header bg-body-tertiary">
        <h5 class="mb-0">Timeline</h5>
    </div>
    <div class="card-body">
        @forelse ($quote->statusLogs as $log)
            <div class="border-bottom pb-3 mb-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div>
                        <span class="fw-semibold">{{ $statusLabels[$log->to_status] ?? $log->to_status }}</span>
                        @if ($log->from_status)
                            <span class="text-body-tertiary"> (de {{ $statusLabels[$log->from_status] ?? $log->from_status }})</span>
                        @endif
                    </div>
                    <small class="text-body-tertiary">{{ optional($log->created_at)->format('Y-m-d H:i') }}</small>
                </div>
                <div class="text-body-tertiary mb-1">
                    {{ $log->performer?->name ?? 'Sistema' }}
                </div>
                @if ($log->message)
                    <div>{{ $log->message }}</div>
                @endif
            </div>
        @empty
            <p class="text-body-tertiary mb-0">Sem eventos registados.</p>
        @endforelse
    </div>
</div>

