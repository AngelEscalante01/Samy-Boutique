<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IdempotentApiOperation
{
    public function handle(Request $request, Closure $next): Response
    {
        // Optional for installed APK compatibility. New clients must persist one
        // key per operation before sending it and reuse it after an uncertain reply.
        $key = $request->header('Idempotency-Key');
        // The installed web/PWA queue already persists a UUID. Protect its
        // existing requests without requiring a client update or new header.
        if ($key === null && $request->routeIs('sync.sales.store') && is_string($request->input('uuid')) && Str::isUuid($request->input('uuid'))) {
            $key = 'legacy-sync:'.strtolower((string) $request->input('uuid'));
        }
        if ($key === null) {
            return $next($request);
        }
        if (! preg_match('/\A[A-Za-z0-9._:-]{16,100}\z/', $key)) {
            return response()->json(['message' => 'Idempotency-Key inválida.'], 422);
        }
        $payload = $request->input();
        if ($request->allFiles() !== []) {
            $payload['__files_sha256'] = $this->fingerprints($request->allFiles());
        }
        $payload = $this->canonical($payload);
        $hash = hash('sha256', json_encode([$request->method(), $request->path(), $payload], JSON_THROW_ON_ERROR));
        DB::beginTransaction();
        try {
            DB::table('api_operation_receipts')->insertOrIgnore([
                'user_id' => $request->user()->id, 'operation_key' => $key,
                'request_hash' => $hash, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $receipt = DB::table('api_operation_receipts')
                ->where('user_id', $request->user()->id)->where('operation_key', $key)
                ->lockForUpdate()->first();
            if (! hash_equals($receipt->request_hash, $hash)) {
                DB::rollBack();
                return response()->json(['message' => 'La identidad de operación ya corresponde a otro contenido.'], 409);
            }
            if ($receipt->response_status !== null) {
                DB::commit();
                return response($receipt->response_body, $receipt->response_status)
                    ->header('Content-Type', 'application/json')->header('Idempotency-Replayed', 'true');
            }
            $response = $next($request);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                DB::rollBack();
                return $response;
            }
            DB::table('api_operation_receipts')->where('id', $receipt->id)->update([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(), 'updated_at' => now(),
            ]);
            DB::commit();
            return $response;
        } catch (\Throwable $exception) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            throw $exception;
        }
    }

    private function fingerprints(array $files): array
    {
        $result = [];
        foreach ($files as $key => $file) {
            $result[$key] = is_array($file) ? $this->fingerprints($file) : [
                'sha256' => hash_file('sha256', $file->getRealPath()),
                'name' => $file->getClientOriginalName(),
            ];
        }
        return $result;
    }

    private function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as &$item) {
            if (is_array($item)) {
                $item = $this->canonical($item);
            }
        }
        return $value;
    }
}
