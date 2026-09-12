<?php

namespace App\Http\Controllers;

use App\Models\ClinicSetting;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $clinic = Schema::hasTable('clinic_settings')
            ? ClinicSetting::query()->first()
            : null;
        $clinic ??= new ClinicSetting(['name' => 'VetCare', 'appointment_minutes' => 30]);

        return response()
            ->view('welcome', compact('clinic'))
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }
}
