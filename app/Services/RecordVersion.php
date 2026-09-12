<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;

class RecordVersion
{
    public static function of(Model $record): string
    {
        return hash_hmac('sha256', json_encode($record->getRawOriginal(), JSON_THROW_ON_ERROR), config('app.key'));
    }
}
