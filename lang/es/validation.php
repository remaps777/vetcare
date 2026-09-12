<?php

return [
    'required' => 'El campo :attribute es obligatorio.',
    'exists' => 'El :attribute seleccionado no es válido.',
    'date_format' => 'El campo :attribute no tiene el formato correcto.',
    'after' => 'El campo :attribute debe ser una fecha posterior a :date.',
    'before_or_equal' => 'El campo :attribute debe ser anterior o igual a :date.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'string' => 'El campo :attribute debe ser texto.',
    'min' => [
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'max' => [
        'string' => 'El campo :attribute no puede tener más de :max caracteres.',
        'numeric' => 'El campo :attribute no puede ser mayor que :max.',
    ],
    'numeric' => 'El campo :attribute debe ser un número.',
    'in' => 'El :attribute seleccionado no es válido.',
    'prohibited' => 'El campo :attribute no está permitido.',
    'attributes' => [
        'owner_id' => 'propietario',
        'pet_id' => 'mascota',
        'doctor_id' => 'médico veterinario',
        'scheduled_at' => 'fecha y hora',
        'consulted_at' => 'fecha y hora de atención',
        'reason' => 'motivo',
        'diagnosis' => 'diagnóstico',
        'status' => 'estado',
        'weight' => 'peso',
        'temperature' => 'temperatura',
    ],
];
