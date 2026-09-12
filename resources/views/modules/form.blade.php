<div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-labelledby="{{ $modalId }}Title" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <form data-confirmed-form method="POST" action="{{ $record ? route($base.'.update', [...($routeParams ?? []), 'record' => $record->id]) : route($base.'.store', $routeParams ?? []) }}">
            @csrf
            @if($record) @method('PATCH') <input type="hidden" name="_version" value="{{ \App\Services\RecordVersion::of($record) }}"> @endif
            <div class="modal-header"><h2 class="modal-title fs-5" id="{{ $modalId }}Title">{{ $record ? 'Editar' : 'Registrar' }} · {{ $title }}</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="alert alert-danger d-none" data-form-errors role="alert"></div>
                <div data-edit-fields><div class="row g-3">
                    @foreach($fields as $key => $field)
                        @php
                            $value = $record ? data_get($record, $key) : ($field['default'] ?? '');
                            $type = $field['type'] ?? 'text';
                            if ($value instanceof \DateTimeInterface) $value = $value->format($type === 'date' ? 'Y-m-d' : 'Y-m-d\TH:i');
                            if (is_bool($value)) $value = (int) $value;
                        @endphp
                        <div class="{{ $type === 'textarea' ? 'col-12' : 'col-md-6' }}">
                            <label class="form-label" for="{{ ($field['type'] ?? null) === 'autocomplete' ? $modalId.'-'.$key.'-autocomplete' : $modalId.'-'.$key }}">{{ $field['label'] }} @if($field['required'] ?? false)<span class="text-danger">*</span>@endif</label>
                            @if(isset($field['options']))
                                @if(($field['type'] ?? null) === 'autocomplete')
                                    @php
                                        $autocompleteId = $modalId.'-'.$key.'-autocomplete';
                                        $menuId = $autocompleteId.'-menu';
                                        $selectedLabel = $field['options'][$value] ?? '';
                                    @endphp
                                    <div class="position-relative" data-autocomplete>
                                        <input class="form-control" type="search" id="{{ $autocompleteId }}" value="{{ $selectedLabel }}" data-autocomplete-input data-hidden-target="{{ $modalId }}-{{ $key }}" data-autocomplete-menu="{{ $menuId }}" placeholder="Seleccionar propietario..." autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="{{ $menuId }}" aria-expanded="false" @required($field['required'] ?? false)>
                                        <div class="dropdown-menu w-100 shadow-sm" id="{{ $menuId }}" data-autocomplete-options role="listbox">
                                            @foreach($field['options'] as $id => $label)
                                                <button type="button" class="dropdown-item text-wrap" data-autocomplete-option data-value="{{ $id }}" data-label="{{ $label }}" role="option">{{ $label }}</button>
                                            @endforeach
                                        </div>
                                    </div>
                                    <input type="hidden" name="{{ $key }}" id="{{ $modalId }}-{{ $key }}" value="{{ $value }}" @if(isset($field['depends_on'])) data-dependent-on="{{ $modalId }}-{{ $field['depends_on'] }}" @endif>
                                @else
                                    <select class="form-select" name="{{ $key }}" id="{{ $modalId }}-{{ $key }}" @if(isset($field['depends_on'])) data-dependent-on="{{ $modalId }}-{{ $field['depends_on'] }}" @endif @disabled($field['disabled'] ?? false) @required($field['required'] ?? false)>
                                        <option value="">Seleccionar</option>
                                        @foreach($field['options'] as $id => $label)<option value="{{ $id }}" @if(isset($field['option_meta'][$id])) data-filter-value="{{ $field['option_meta'][$id] }}" @endif @selected((string)$value === (string)$id)>{{ $label }}</option>@endforeach
                                    </select>
                                    @if($field['disabled'] ?? false)<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                                @endif
                            @elseif($type === 'textarea')
                                <textarea class="form-control" name="{{ $key }}" id="{{ $modalId }}-{{ $key }}" rows="3" @required($field['required'] ?? false)>{{ $value }}</textarea>
                            @else
                                <input class="form-control" type="{{ $type }}" name="{{ $key }}" id="{{ $modalId }}-{{ $key }}" value="{{ $value }}" step="{{ $field['step'] ?? 'any' }}" @if(isset($field['min'])) min="{{ $field['min'] }}" @endif @if(isset($field['max'])) max="{{ $field['max'] }}" @endif @required($field['required'] ?? false)>
                            @endif
                            @isset($field['help'])<div class="form-text">{{ $field['help'] }}</div>@endisset
                        </div>
                    @endforeach
                </div></div>
                <div class="d-none" data-confirmation><p class="fw-semibold">Confirma los datos revisados por el servidor</p><dl class="row mb-0" data-summary></dl><p class="small text-secondary mt-3">La operación se valida con tu sesión, permisos y datos actuales de la base de datos.</p></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary d-none" data-back-to-edit>Volver a editar</button><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="submit" class="btn btn-primary" data-save-button>Revisar y continuar</button></div>
        </form>
    </div></div>
</div>
