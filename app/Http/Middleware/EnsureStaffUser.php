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

        if (!$user->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json(['error' => 'Account suspended. Please contact administrator.'], 403);
            }
            return redirect()->route('portal.login')->withErrors(['email' => 'Your account has been deactivated. Please contact the administrator.']);
        }

        if (!$user->isStaff()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. Web Portal access is restricted to IT Staff and Management.'], 403);
            }
            abort(403, 'Unauthorized. Web Portal access is restricted to IT Staff and Management.');
        }

        if (!$user->can_access_portal) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Unauthorized. Web Portal access is not enabled for your account.'], 403);
            }
            abort(403, 'Unauthorized. Web Portal access is not enabled for this account.');
        }

        return $next($request);
    }
}
