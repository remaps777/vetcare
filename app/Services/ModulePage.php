<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

class ModulePage
{
    public static function catalogRecords(Builder $query): LengthAwarePaginator
    {
        request()->validate(['active' => ['nullable', Rule::in(['0', '1'])]]);

        return $query->when(request()->filled('active'), fn (Builder $query) => $query->where('is_active', request()->boolean('active')))->paginate(15)->withQueryString();
    }

    /**
     * @param  array<string,mixed>  $fields
     * @param  array<string,string>  $columns
     * @param  array<string,mixed>  $routeParams  Extra route parameters (e.g. ['catalog' => 'species'])
     */
    public static function render(
        string $title,
        LengthAwarePaginator $records,
        array $fields,
        array $columns,
        string $base,
        bool $canCreate = true,
        bool $canEdit = true,
        bool $sensitive = true,
        string $description = '',
        array $routeParams = [],
        ?array $formFields = null,
        bool $canDelete = false,
        bool $canUpdateStatus = false,
        bool $canAttendOrder = false,
        mixed $serviceOrder = null,
        bool $canEmergencyOrder = false,
        mixed $emergencySpecies = null,
    ): View {
        $formFields ??= $fields;
        $catalogPermissions = ['species' => 'catalogos', 'breeds' => 'catalogos', 'specialties' => 'especialidades', 'item-types' => 'tipos_articulo', 'distributors' => 'distribuidores', 'products' => 'productos', 'medications' => 'medicamentos'];
        $catalog = substr($base, strrpos($base, '.') + 1);
        $statusCatalog = isset($catalogPermissions[$catalog]) && str_starts_with($base, 'admin.') ? $catalog : null;
        if ($statusCatalog) {
            $canCreate = auth()->user()->hasPermission($catalogPermissions[$catalog].(in_array($catalog, ['species', 'breeds']) ? '.editar' : '.crear'));
            $canEdit = auth()->user()->hasPermission($catalogPermissions[$catalog].'.editar');
        }

        return view('modules.index', ['statusCatalog' => $statusCatalog, 'title' => $title, 'records' => $records, 'fields' => $formFields, 'columns' => $columns, 'base' => $base, 'canCreate' => $canCreate, 'canEdit' => $canEdit, 'canDelete' => $canDelete, 'canUpdateStatus' => $canUpdateStatus, 'canAttendOrder' => $canAttendOrder, 'serviceOrder' => $serviceOrder, 'canEmergencyOrder' => $canEmergencyOrder, 'emergencySpecies' => $emergencySpecies, 'sensitive' => $sensitive, 'description' => $description, 'routeParams' => $routeParams]);
    }
}
