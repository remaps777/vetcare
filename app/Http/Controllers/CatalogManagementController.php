<?php

namespace App\Http\Controllers;

use App\Models\Breed;
use App\Models\Distributor;
use App\Models\ItemType;
use App\Models\Medication;
use App\Models\Product;
use App\Models\Specialty;
use App\Models\Species;
use App\Services\ConfirmedMutation;
use App\Services\ModulePage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogManagementController extends Controller
{
    public function distributors(): View
    {
        return ModulePage::render('Distribuidores', ModulePage::catalogRecords(Distributor::query()->latest('id')), [
            'ruc' => ['label' => 'RUC', 'required' => true, 'min' => 11, 'max' => 11],
            'business_name' => ['label' => 'Razón social', 'required' => true],
            'trade_name' => ['label' => 'Nombre comercial'],
            'address' => ['label' => 'Ubicación / Dirección'],
            'phone' => ['label' => 'Teléfono', 'type' => 'number', 'min' => 0],
            'email' => ['label' => 'Correo', 'type' => 'email'],
            'description' => ['label' => 'Descripción', 'type' => 'textarea'],
            'is_active' => ['label' => 'Estado', 'options' => [1 => 'Activo', 0 => 'Inactivo'], 'default' => 1],
        ], ['ruc' => 'RUC', 'business_name' => 'Razón social', 'trade_name' => 'Nombre comercial', 'address' => 'Ubicación', 'phone' => 'Teléfono', 'is_active' => 'Estado'], 'admin.distributors', description: 'Gestiona distribuidores sin eliminar los que ya forman parte del historial.');
    }

    public function saveDistributor(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $record = $request->route('record') ? Distributor::findOrFail($request->route('record')) : null;
        $data = $request->validate([
            'ruc' => ['required', 'digits:11', Rule::unique('distributors')->ignore($record?->id)],
            'business_name' => ['required', 'string', 'max:180'],
            'trade_name' => ['nullable', 'string', 'max:180'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'digits_between:7,15'],
            'email' => ['nullable', 'email', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $mutation->handle($request, $record, $data, function ($locked, $values): Distributor {
            $saved = $locked ?? new Distributor;
            $saved->fill($values)->save();

            return $saved;
        }, ['RUC' => $data['ruc'], 'Razón social' => $data['business_name'], 'Estado' => $data['is_active'] ? 'Activo' : 'Inactivo'], true);
    }

    public function itemTypes(): View
    {
        return ModulePage::render('Tipos de artículo', ModulePage::catalogRecords(ItemType::query()->orderBy('name')), [
            'name' => ['label' => 'Nombre', 'required' => true],
            'applies_to' => ['label' => 'Aplica a', 'required' => true, 'options' => ['PRODUCT' => 'Producto', 'MEDICATION' => 'Medicamento', 'BOTH' => 'Ambos']],
            'description' => ['label' => 'Descripción', 'type' => 'textarea'],
            'is_active' => ['label' => 'Estado', 'options' => [1 => 'Activo', 0 => 'Inactivo'], 'default' => 1],
        ], ['name' => 'Nombre', 'applies_to_label' => 'Aplica a', 'description' => 'Descripción', 'is_active' => 'Estado'], 'admin.item-types', description: 'Define tipos controlados para productos y medicamentos.');
    }

    public function saveItemType(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $record = $request->route('record') ? ItemType::findOrFail($request->route('record')) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('item_types')->where(fn ($query) => $query->where('applies_to', $request->input('applies_to')))->ignore($record?->id)],
            'applies_to' => ['required', Rule::in(['PRODUCT', 'MEDICATION', 'BOTH'])],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $mutation->handle($request, $record, $data, function ($locked, $values): ItemType {
            $saved = $locked ?? new ItemType;
            $saved->fill($values)->save();

            return $saved;
        }, ['Nombre' => $data['name'], 'Aplica a' => ['PRODUCT' => 'Producto', 'MEDICATION' => 'Medicamento', 'BOTH' => 'Ambos'][$data['applies_to']], 'Estado' => $data['is_active'] ? 'Activo' : 'Inactivo'], true);
    }

    public function specialties(): View
    {
        return ModulePage::render('Especialidades', ModulePage::catalogRecords(Specialty::query()->orderBy('name')), [
            'name' => ['label' => 'Nombre', 'required' => true],
            'description' => ['label' => 'Descripción', 'type' => 'textarea'],
            'is_active' => ['label' => 'Estado', 'options' => [1 => 'Activo', 0 => 'Inactivo'], 'default' => 1],
        ], ['name' => 'Nombre', 'description' => 'Descripción', 'is_active' => 'Estado'], 'admin.specialties', description: 'Administra las especialidades veterinarias disponibles para el registro profesional.');
    }

    public function saveSpecialty(Request $request, ConfirmedMutation $mutation): JsonResponse
    {
        $record = $request->route('record') ? Specialty::findOrFail($request->route('record')) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150', Rule::unique('specialties')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
        ]);

        return $mutation->handle($request, $record, $data, function ($locked, $values): Specialty {
            $saved = $locked ?? new Specialty;
            $saved->fill($values)->save();

            return $saved;
        }, ['Nombre' => $data['name'], 'Estado' => $data['is_active'] ? 'Activo' : 'Inactivo'], true);
    }

    public function status(Request $request, string $catalog, int $record, ConfirmedMutation $mutation): JsonResponse
    {
        $catalogs = [
            'species' => [Species::class, 'catalogos.editar'],
            'breeds' => [Breed::class, 'catalogos.editar'],
            'specialties' => [Specialty::class, 'especialidades.editar'],
            'item-types' => [ItemType::class, 'tipos_articulo.editar'],
            'distributors' => [Distributor::class, 'distribuidores.editar'],
            'products' => [Product::class, 'productos.editar'],
            'medications' => [Medication::class, 'medicamentos.editar'],
        ];
        abort_unless(isset($catalogs[$catalog]), 404);
        [$model, $permission] = $catalogs[$catalog];
        abort_unless($request->user()->hasPermission($permission), 403);
        $target = $model::findOrFail($record);
        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        return $mutation->handle($request, $target, $data, function ($locked, $values) {
            $locked->update($values);

            return $locked;
        }, ['Registro' => $target->name ?? $target->business_name, 'Estado' => $data['is_active'] ? 'Activo' : 'Inactivo'], true);
    }
}
