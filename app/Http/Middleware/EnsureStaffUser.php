<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect()->route('portal.login');
        }

        $user = Auth::user();

        if (!$user->isStaff()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. Staff privileges required.'], 403);
            }
            abort(403, 'Unauthorized. Staff portal access requires Help Desk, Technician, HOD, or Administrator role.');
        }

        return $next($request);
    }
}
