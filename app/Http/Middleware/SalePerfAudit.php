<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/** Local, opt-in timings for the checkout audit. No SQL or sale data is logged. */
class SalePerfAudit
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('local') || ! $request->is('api/sales')
            || ! $request->isMethod('POST') || $request->header('X-Perf-Audit') !== '1') {
            return $next($request);
        }

        $start = hrtime(true);
        $request->attributes->set('perf_t2', $start);
        $queries = 0;
        $queryMs = 0.0;
        $slowestMs = 0.0;
        DB::listen(function ($query) use (&$queries, &$queryMs, &$slowestMs): void {
            $queries++;
            $queryMs += $query->time;
            $slowestMs = max($slowestMs, $query->time);
        });

        $response = $next($request);
        $end = hrtime(true);
        $transactionStart = $request->attributes->get('perf_t3');
        $transactionEnd = $request->attributes->get('perf_t4');
        $serializationStart = $request->attributes->get('perf_serialization_start');
        $serializationEnd = $request->attributes->get('perf_serialization_end');

        $response->headers->set('X-Perf-Laravel-Ms', $this->ms($end - $start));
        $response->headers->set('X-Perf-Queries', (string) $queries);
        $response->headers->set('X-Perf-Query-Ms', number_format($queryMs, 3, '.', ''));
        $response->headers->set('X-Perf-Slowest-Query-Ms', number_format($slowestMs, 3, '.', ''));
        if ($transactionStart && $transactionEnd) {
            $response->headers->set('X-Perf-Transaction-Ms',
                $this->ms($transactionEnd - $transactionStart));
        }
        if ($serializationStart && $serializationEnd) {
            $response->headers->set('X-Perf-Serialization-Ms',
                $this->ms($serializationEnd - $serializationStart));
        }
        $response->headers->set('X-Perf-Response-Bytes',
            (string) strlen((string) $response->getContent()));

        return $response;
    }

    private function ms(int $nanoseconds): string
    {
        return number_format($nanoseconds / 1_000_000, 3, '.', '');
    }
}
