<div class="row g-3 mb-4">
    @foreach($stats as $label => $value)
        <div class="col-12 col-sm-6 col-xl">
            <div class="card card-stat h-100 p-3">
                <div class="d-flex align-items-center gap-3">
                    <span class="stat-icon bg-primary-subtle-vet flex-shrink-0"><i class="bi bi-{{ $icons[$loop->index] }}" aria-hidden="true"></i></span>
                    <div><div class="small text-secondary">{{ $label }}</div><div class="fs-3 fw-bold">{{ $value }}</div></div>
                </div>
            </div>
        </div>
    @endforeach
</div>
