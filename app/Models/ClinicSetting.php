<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicSetting extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'appointment_minutes',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'whatsapp_number',
    ];

    public function getWhatsappUrlAttribute(): ?string
    {
        return $this->whatsapp_number ? 'https://wa.me/'.$this->whatsapp_number : null;
    }
}
