<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;

class HealthController extends Controller
{
    public function piston()
    {
        // Default to public Piston endpoint if env not configured (dev-friendly)
        $base = (string) env('PISTON_URL', 'https://emkc.org/api/v2/piston');
        $baseUrl = rtrim($base, '/');
        $endpoint = $baseUrl . '/runtimes';

        $t0 = microtime(true);
        try {
            $resp = Http::timeout(5)->get($endpoint);
            $ms = (int) round((microtime(true) - $t0) * 1000);
            return response()->json([
                'ok' => $resp->ok(),
                'status' => $resp->status(),
                'latency_ms' => $ms,
                'base_url' => $baseUrl,
                'endpoint' => $endpoint,
            ], $resp->ok() ? 200 : 500);
        } catch (\Throwable $e) {
            $ms = (int) round((microtime(true) - $t0) * 1000);
            return response()->json([
                'ok' => false,
                'error' => $e->getMessage(),
                'latency_ms' => $ms,
                'base_url' => $baseUrl,
                'endpoint' => $endpoint,
            ], 500);
        }
    }

    // Judge0 health check removed - using local Java execution only
}


