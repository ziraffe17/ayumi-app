<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class CsvExportRateLimit
{
    public function handle(Request $request, Closure $next): mixed
    {
        $userId = auth()->id();
        $key = "csv_export_rate_limit:{$userId}";

        // 1分間に3回まで
        if (RateLimiter::tooManyAttempts($key, 3)) {
            return response()->json([
                'success' => false,
                'message' => 'CSV出力の回数制限に達しました。しばらくお待ちください。',
            ], 429);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}