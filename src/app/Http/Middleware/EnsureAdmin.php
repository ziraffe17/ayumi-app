<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $u = auth('staff')->user();
        if (!$u || $u->role !== 'admin') {
            // 403 を監査に落とす（AuditTrailMiddlewareでも拾えるが念のため）
            abort(403, 'Forbidden');
        }
        return $next($request);
    }
}
