<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Breed;
use App\Models\Pet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ApiOwnerPetController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $pets = Pet::with(['species:id,name', 'breed:id,species_id,name'])
            ->where('owner_id', $request->user()->owner?->id ?? 0)
            ->where('is_active', true)
            ->orderBy('name')
            ->paginate(15);

        return response()->json([
            'data' => $pets->items(),
            'meta' => [
                'current_page' => $pets->currentPage(),
                'last_page' => $pets->lastPage(),
                'per_page' => $pets->perPage(),
                'total' => $pets->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);
        $ownerId = $request->user()->owner?->id;

        abort_unless($ownerId, 403, 'El usuario no tiene un perfil de propietario.');

        $pet = DB::transaction(function () use ($data, $ownerId, $request): Pet {
            $pet = Pet::create([...$data, 'owner_id' => $ownerId]);
            $this->audit($request, $pet, null);

            return $pet;
        });

        return $this->petResponse($pet, 201);
    }

    public function show(Request $request, int $pet): JsonResponse
    {
        return $this->petResponse($this->ownedPet($request, $pet));
    }

    public function update(Request $request, int $pet): JsonResponse
    {
        $record = $this->ownedPet($request, $pet);
        $data = $this->validatedData($request, $record);

        DB::transaction(function () use ($data, $record, $request): void {
            $before = $record->attributesToArray();
            $record->update($data);
            $this->audit($request, $record, $before);
        });

        return $this->petResponse($record->fresh());
    }

    public function destroy(Request $request, int $pet): JsonResponse
    {
        $record = $this->ownedPet($request, $pet);
        abort_if(
            $record->appointments()->exists() || $record->consultations()->exists(),
            409,
            'No puedes eliminar una mascota con citas o consultas relacionadas. Puedes desactivarla desde editar.',
        );

        DB::transaction(function () use ($record, $request): void {
            $before = $record->attributesToArray();
            $record->delete();
            $this->audit($request, $record, $before);
        });

        return response()->json(['message' => 'Mascota eliminada correctamente zzzzzzzzzzz.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Pet $pet = null): array
    {
        $required = $request->isMethod('put') ? 'required' : 'sometimes';

        $data = $request->validate([
            'name' => [$required, 'string', 'max:100'],
            'species_id' => [$required, 'integer', Rule::exists('species', 'id')->where('is_active', true)],
            'breed_id' => [$required, 'integer', Rule::exists('breeds', 'id')->where('is_active', true)],
            'sex' => [$required, Rule::in(Pet::SEXES)],
            'birth_date' => ['nullable', 'date'],
            'weight' => ['nullable', 'numeric', 'between:0,9999.99'],
            'color' => ['nullable', 'string', 'max:50'],
            'observations' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['sometimes', 'boolean'],
            'owner_id' => ['prohibited'],
        ]);

        $speciesId = $data['species_id'] ?? $pet?->species_id;
        $breedId = $data['breed_id'] ?? $pet?->breed_id;
        $breed = Breed::whereKey($breedId)
            ->where('species_id', $speciesId)
            ->where('is_active', true)
            ->first();

        abort_unless($breed, 422, 'La raza seleccionada no pertenece a la especie indicada o está inactiva.');

        return $data;
    }

    private function ownedPet(Request $request, int $pet): Pet
    {
        return Pet::with(['species:id,name', 'breed:id,species_id,name'])
            ->whereKey($pet)
            ->where('owner_id', $request->user()->owner?->id ?? 0)
            ->firstOrFail();
    }

    private function petResponse(Pet $pet, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $pet->load(['species:id,name', 'breed:id,species_id,name'])], $status);
    }

    /**
     * @param  array<string, mixed>|null  $before
     */
    private function audit(Request $request, Pet $pet, ?array $before): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $request->method().' '.$request->path(),
            'subject_type' => $pet->getTable(),
            'subject_id' => $pet->id,
            'before_data' => $before,
            'after_data' => $pet->exists ? $pet->fresh()->attributesToArray() : ['deleted' => true],
        ]);
    }
}
