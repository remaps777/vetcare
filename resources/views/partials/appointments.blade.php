<section class="vet-card p-4 mt-4">
    <h2 class="h5 fw-bold mb-3">Próximas citas</h2>
    @forelse($appointments as $appointment)
        <div class="d-flex flex-wrap justify-content-between gap-2 py-3 border-bottom">
            <div><strong>{{ $appointment->pet->name }}</strong><div class="small text-secondary">{{ $appointment->pet->owner?->full_name ?? 'Sin propietario' }} · {{ $appointment->reason }} · Dr. {{ $appointment->doctor?->user?->name ?? 'Por asignar' }}</div></div>
            <time datetime="{{ $appointment->scheduled_at->toIso8601String() }}">{{ $appointment->scheduled_at->format('d/m/Y H:i') }}</time>
        </div>
    @empty
        <p class="text-secondary mb-0">No tienes citas próximas programadas.</p>
    @endforelse
</section>
