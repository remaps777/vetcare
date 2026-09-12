<?php

namespace Database\Seeders;

use App\Models\Breed;
use App\Models\Distributor;
use App\Models\DoctorProfile;
use App\Models\ItemType;
use App\Models\Medication;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Product;
use App\Models\Specialty;
use App\Models\Species;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $specialties = [];
        foreach ([
            'Medicina general veterinaria',
            'Cirugía veterinaria',
            'Dermatología veterinaria',
            'Medicina interna',
            'Traumatología y ortopedia veterinaria',
            'Oftalmología veterinaria',
            'Cardiología veterinaria',
            'Diagnóstico por imágenes',
            'Animales menores',
            'Medicina preventiva',
        ] as $name) {
            $specialties[$name] = Specialty::updateOrCreate(['name' => $name], [
                'description' => 'Especialidad veterinaria de pruebas para la clínica.',
                'is_active' => true,
            ]);
        }

        $andina = Distributor::updateOrCreate(['ruc' => '20600000001'], [
            'business_name' => 'Distribuidora Veterinaria Andina S.A.C. - DEMO',
            'trade_name' => 'VetAndina Demo',
            'address' => 'Lima',
            'phone' => '014500001',
            'email' => 'ventas@vetandina.demo',
            'description' => 'Distribución de medicamentos e insumos veterinarios.',
            'is_active' => true,
        ]);
        $petSupply = Distributor::updateOrCreate(['ruc' => '20600000002'], [
            'business_name' => 'Pet Supply Perú S.A.C. - DEMO',
            'trade_name' => 'PetSupply Demo',
            'address' => 'Ate, Lima',
            'phone' => '014500002',
            'email' => 'pedidos@petsupply.demo',
            'description' => 'Distribución de alimentos y accesorios para mascotas.',
            'is_active' => true,
        ]);

        $species = [];
        foreach ([
            'Canino' => ['Labrador Retriever', 'Pastor Alemán', 'Beagle', 'Bulldog Francés', 'Mestizo'],
            'Felino' => ['Persa', 'Siamés', 'Maine Coon', 'British Shorthair', 'Mestizo'],
            'Ave' => ['Canario', 'Periquito', 'Ninfa'],
            'Conejo' => ['Enano Holandés', 'Rex', 'Cabeza de León'],
        ] as $speciesName => $breeds) {
            $species[$speciesName] = Species::updateOrCreate(['name' => $speciesName], ['is_active' => true]);
            foreach ($breeds as $breedName) {
                Breed::updateOrCreate(['species_id' => $species[$speciesName]->id, 'name' => $breedName], ['is_active' => true]);
            }
        }

        foreach (range(1, 5) as $number) {
            $user = User::updateOrCreate(['username' => 'dueno'.$number], [
                'name' => 'Propietario Demo '.$number,
                'email' => 'dueno'.$number.'@vetcare.local',
                'password' => '12345678',
                'role' => User::ROLE_OWNER,
                'is_active' => true,
            ]);
            $owner = Owner::updateOrCreate(['user_id' => $user->id], [
                'dni' => '7000000'.$number,
                'first_name' => 'Propietario',
                'last_name' => 'Demo '.$number,
                'phone' => '90000000'.$number,
                'email' => $user->email,
                'is_active' => true,
            ]);
            $speciesModel = $species[$number % 2 === 0 ? 'Felino' : 'Canino'];
            $breed = $speciesModel->breeds()->orderBy('id')->firstOrFail();
            foreach (range(1, 2) as $petNumber) {
                Pet::updateOrCreate(['owner_id' => $owner->id, 'name' => 'Mascota '.$number.'-'.$petNumber], [
                    'species_id' => $speciesModel->id,
                    'breed_id' => $breed->id,
                    'sex' => $petNumber === 1 ? Pet::SEX_MACHO : Pet::SEX_HEMBRA,
                    'is_active' => true,
                ]);
            }
        }

        foreach (range(1, 4) as $number) {
            $user = User::updateOrCreate(['username' => 'doctor'.$number], [
                'name' => 'Doctor Demo '.$number,
                'email' => 'doctor'.$number.'@vetcare.local',
                'password' => '12345678',
                'role' => User::ROLE_DOCTOR,
                'is_active' => true,
            ]);

            $specialtyName = [
                'Medicina general veterinaria',
                'Cirugía veterinaria',
                'Dermatología veterinaria',
                'Medicina interna',
            ][$number - 1] ?? 'Medicina general veterinaria';

            $profile = DoctorProfile::updateOrCreate(['user_id' => $user->id], [
                'dni' => '6000000'.$number,
                'license_number' => 'CMVP-DEMO-'.$number,
                'specialty_id' => $specialties[$specialtyName]->id,
                'specialty' => $specialtyName,
                'phone' => '91000000'.$number,
                'approval_status' => DoctorProfile::STATUS_APPROVED,
            ]);
            if (! $profile->isApproved()) {
                $profile->approve();
            }
        }

        foreach ([
            ['COLLAR-001', 'Collar antipulgas', 'Antipulgas', 'Talla mediana', 4500],
            ['PELOTA-001', 'Pelota de goma', 'Juguete', 'Unidad', 1800],
            ['SHAMP-001', 'Shampoo dermatológico', 'Higiene', 'Frasco 250 ml', 3200],
            ['CORREA-001', 'Correa ajustable', 'Accesorio', 'Unidad', 2800],
            ['ALIMENTO-001', 'Alimento premium', 'Alimento', 'Bolsa 2 kg', 12500],
        ] as [$code, $name, $type, $presentation, $price]) {
            Product::updateOrCreate(['code' => $code], [
                'name' => $name,
                'type' => $type,
                'presentation' => $presentation,
                'description' => 'Producto de demostración para la tienda de la clínica.',
                'sale_price_cents' => $price,
                'stock' => 20,
                'minimum_stock' => 5,
                'is_active' => true,
            ]);
        }

        $productTypes = ItemType::whereIn('name', ['Alimento', 'Higiene', 'Antipulgas', 'Juguete'])->get()->keyBy('name');
        foreach ([
            ['ALIMENTO-001', 'Alimento premium canino adulto', 'Alimento', 2, 'kg', 12500],
            ['SHAMP-001', 'Shampoo dermatológico para mascotas', 'Higiene', 250, 'ml', 3200],
            ['COLLAR-001', 'Collar antipulgas', 'Antipulgas', 1, 'unidad', 4500],
            ['PELOTA-001', 'Pelota de goma resistente', 'Juguete', 1, 'unidad', 1800],
        ] as [$code, $name, $type, $quantity, $unit, $price]) {
            Product::where('code', $code)->update([
                'distributor_id' => $petSupply->id,
                'item_type_id' => $productTypes[$type]?->id,
                'presentation_quantity' => $quantity,
                'presentation_unit' => $unit,
                'presentation' => $quantity.' '.$unit,
                'stock' => 0,
            ]);
        }

        $medicationTypes = ItemType::whereIn('name', ['Antibiótico', 'Antiinflamatorio', 'Antiséptico', 'Antiparasitario'])->get()->keyBy('name');
        foreach ([
            ['MED-AMOX-001', 'Amoxicilina', 'Antibiótico', 20, 'tableta', 2000],
            ['MED-MELOX-001', 'Meloxicam veterinario', 'Antiinflamatorio', 10, 'ml', 2800],
            ['MED-IODO-001', 'Solución antiséptica veterinaria', 'Antiséptico', 120, 'ml', 1200],
            ['MED-ANTI-001', 'Antiparasitario veterinario', 'Antiparasitario', 4, 'tableta', 1800],
        ] as [$code, $name, $type, $quantity, $unit, $price]) {
            Medication::updateOrCreate(['code' => $code], [
                'name' => $name,
                'item_type_id' => $medicationTypes[$type]?->id,
                'distributor_id' => $andina->id,
                'presentation_quantity' => $quantity,
                'presentation_unit' => $unit,
                'presentation' => $quantity.' '.$unit,
                'purchase_price_cents' => $price,
                'minimum_stock' => 5,
                'stock' => 0,
                'is_active' => true,
            ]);
        }
    }
}
