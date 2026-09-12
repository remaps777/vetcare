<?php

namespace App\Http\Controllers;

use App\Models\Breed;
use App\Models\Species;
use App\Services\ConfirmedMutation;
use App\Services\ModulePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function index(Request $request): View
    {
        $breed = str_ends_with((string) $request->route()->getName(), '.breeds');
        $query = $breed ? Breed::with('species') : Species::query();
        $fields = [
            'name' => ['label' => 'Nombre', 'required' => true],
            'is_active' => ['label' => 'Estado', 'options' => [1 => 'Activo', 0 => 'Inactivo']],
        ];
        $columns = ['name' => 'Nombre', 'is_active' => 'Activo'];

        if ($breed) {
            $fields['species_id'] = [
                'label' => 'Especie',
                'required' => true,
                'options' => Species::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            ];
            $columns['species.name'] = 'Especie';
        }

        $role = strtolower($request->user()->effectiveRole());
        $canManage = $request->user()->isAdmin() || $request->user()->isDoctor();
        $catalog = $breed ? 'breeds' : 'species';

        // Base route name used by modules.form to build store/update URLs
        $base = $role.'.'.$catalog;

        return ModulePage::render(
            $breed ? 'Razas' : 'Especies',
            ModulePage::catalogRecords($query->orderBy('name')),
            $fields,
            $columns,
            $base,
            canCreate: $canManage,
            canEdit: $canManage,
            sensitive: true,
            description: 'Configura los catálogos de la clínica. Los registros utilizados se conservan; puedes desactivarlos.',
            routeParams: [],
        );
    }

    public function save(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        abort_unless($request->user()->isAdmin() || $request->user()->isDoctor(), 403);

        $breed = str_contains((string) $request->route()->getName(), '.breeds.');
        $model = $breed ? Breed::class : Species::class;
        $record = $request->route('record') ? $model::findOrFail($request->route('record')) : null;

        $unique = Rule::unique($breed ? 'breeds' : 'species', 'name')->ignore($record?->id);
        if ($breed) {
            $unique->where('species_id', $request->input('species_id'));
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', $unique],
            'is_active' => ['required', 'boolean'],
            'species_id' => [$breed ? 'required' : 'exclude', 'integer', Rule::exists('species', 'id')->where('is_active', true)],
        ]);

        return DB::transaction(function () use ($breed, $record, $data, $request, $mutation, $model): JsonResponse {
            $speciesName = null;
            if ($breed) {
                $species = Species::whereKey($data['species_id'])->lockForUpdate()->firstOrFail();
                $speciesName = $species->name;
                abort_unless($species->is_active, 409, 'La especie está inactiva.');
                if ($record && (int) $record->species_id !== (int) $species->id) {
                    abort_if($record->pets()->exists(), 409, 'Una raza con mascotas registradas no puede cambiar de especie.');
                }
            }

            return $mutation->handle(
                $request,
                $record,
                $data,
                function ($locked, $values) use ($model) {
                    $saved = $locked ?? new $model;
                    $saved->fill($values)->save();

                    return $saved;
                },
                ['Nombre' => $data['name'], 'Estado' => $data['is_active'] ? 'Activo' : 'Inactivo', ...($breed ? ['Especie' => $speciesName] : [])],
                true,
            );
        });
    }
}
