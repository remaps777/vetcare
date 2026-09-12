<?php

namespace App\Services;

use App\Models\AuditLog;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConfirmedMutation
{
    public function handle(Request $request, ?Model $record, array $data, Closure $save, array $summary, bool $sensitive = false): JsonResponse
    {
        try {
            return DB::transaction(function () use ($request, $record, $data, $save, $summary): JsonResponse {
                $locked = $record?->newQuery()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
                if ($locked) {
                    abort_unless(hash_equals(RecordVersion::of($locked), (string) $request->input('_version')), 409, 'El registro cambió. Recarga la página y revisa los datos antes de confirmar.');
                }
                $digest = hash('sha256', json_encode([$request->user()->id, $request->path(), $request->method(), $data, $locked ? RecordVersion::of($locked) : null], JSON_THROW_ON_ERROR));
                if ($request->boolean('_preview')) {
                    $id = (string) Str::uuid();
                    DB::table('operation_confirmations')->insert(['id' => $id, 'user_id' => $request->user()->id, 'digest' => $digest, 'expires_at' => now()->addMinutes(5)]);

                    return response()->json(['confirmation_token' => $id, 'summary' => $summary, 'message' => 'Revisa los datos. La confirmación vence en 5 minutos.']);
                }
                $request->validate(['confirmation_token' => ['required', 'uuid']]);
                $confirmation = DB::table('operation_confirmations')->where('id', $request->input('confirmation_token'))->where('user_id', $request->user()->id)->lockForUpdate()->first();
                abort_unless($confirmation && ! $confirmation->consumed_at && $confirmation->expires_at > now()->toDateTimeString() && hash_equals($confirmation->digest, $digest), 409, 'La confirmación venció, ya fue utilizada o los datos cambiaron. Revisa nuevamente la operación.');
                $before = $locked?->attributesToArray();
                $saved = $save($locked, $data);
                $after = $saved->exists ? $saved->fresh()->attributesToArray() : ['deleted' => true];
                AuditLog::create(['user_id' => $request->user()->id, 'action' => $request->method().' '.$request->path(), 'subject_type' => $saved->getTable(), 'subject_id' => $saved->id, 'before_data' => $before, 'after_data' => $after]);
                DB::table('operation_confirmations')->where('id', $confirmation->id)->update(['consumed_at' => now()]);

                return response()->json(['message' => 'Operación guardada correctamente.']);
            });
        } catch (QueryException $exception) {
            if (in_array((string) $exception->getCode(), ['23000', '23505', '2601', '2627'], true)) {
                abort(409, 'El dato ya existe o tiene registros relacionados. Actualiza la página para revisar.');
            }
            throw $exception;
        }
    }
}
