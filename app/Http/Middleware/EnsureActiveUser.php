<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            if (!$user->is_active) {
                // Instantly purge user sessions and log out
                try {
                    DB::table('sessions')->where('user_id', $user->id)->delete();
                } catch (\Throwable $e) {}

                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->is('tma/*')) {
                    return response()->json([
                        'status' => 'suspended',
                        'message' => 'Your account has been suspended by an administrator. Please contact the IT Helpdesk.',
                        'error' => 'Your account has been suspended by an administrator. Please contact the IT Helpdesk.',
                    ], 403);
                }

                return redirect()->route('portal.login')->withErrors([
                    'email' => 'Your account has been deactivated. Please contact the administrator.',
                ]);
            }
        }

        return $next($request);
    }
}

