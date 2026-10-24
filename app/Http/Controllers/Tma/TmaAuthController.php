<?php

namespace App\Http\Controllers\Tma;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TelegramLinkRequest;
use App\Models\User;
use App\Services\TelegramAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class TmaAuthController extends Controller
{
    public function __construct(
        protected TelegramAuthService $telegramAuth
    ) {}

    /**
     * Authenticate via Telegram WebApp initData or existing web session.
     */
    public function authenticate(Request $request): JsonResponse
    {
        // 0. If user already has an active web session
        if (Auth::check()) {
            $user = Auth::user();
            $user->loadMissing('employee.branch', 'employee.department');
            return response()->json([
                'status' => 'authenticated',
                'login_method' => 'web_session',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'telegram_id' => $user->telegram_id,
                    'telegram_username' => $user->telegram_username,
                    'employee_code' => $user->employee->employee_code ?? 'EMP',
                    'branch' => $user->employee->branch->name ?? 'Headquarters',
                    'department' => $user->employee->department->name ?? 'General',
                ],
            ]);
        }

        $initData = $request->input('init_data', '');

        // 1. In local environment, allow mock initData for easy browser testing
        if (app()->environment('local') && $request->has('dev_employee_code')) {
            $employee = Employee::where('employee_code', $request->input('dev_employee_code'))->first();
            if ($employee && $employee->user) {
                Auth::login($employee->user);
                return response()->json([
                    'status' => 'authenticated',
                    'user' => [
                        'id' => $employee->user->id,
                        'name' => $employee->user->name,
                        'email' => $employee->user->email,
                        'employee_code' => $employee->employee_code,
                        'branch' => $employee->branch->name ?? 'N/A',
                        'department' => $employee->department->name ?? 'N/A',
                    ],
                ]);
            }
        }

        if (empty($initData)) {
            // When opened directly in browser or when Telegram is down
            return response()->json([
                'status' => 'web_login_required',
                'message' => 'Please sign in with your corporate credentials or access via Telegram.',
            ]);
        }

        // 2. Validate cryptographic hash & 24h freshness
        $tgUser = $this->telegramAuth->validateInitData($initData);

        if (!$tgUser || !isset($tgUser['id'])) {
            return response()->json(['error' => 'Invalid or expired Telegram signature'], 401);
        }

        $telegramId = (int) $tgUser['id'];

        // 3. Find user linked to this telegram_id
        $user = User::where('telegram_id', $telegramId)->where('is_active', true)->with('employee.branch', 'employee.department')->first();

        if ($user) {
            Auth::login($user);

            return response()->json([
                'status' => 'authenticated',
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'telegram_id' => $user->telegram_id,
                    'employee_code' => $user->employee->employee_code ?? 'N/A',
                    'branch' => $user->employee->branch->name ?? 'N/A',
                    'department' => $user->employee->department->name ?? 'N/A',
                ],
            ]);
        }

        // 4. Not linked yet: Check if a pending link request exists
        $pendingRequest = TelegramLinkRequest::where('telegram_id', $telegramId)
            ->where('status', 'pending')
            ->first();

        return response()->json([
            'status' => 'unlinked',
            'has_pending_request' => (bool) $pendingRequest,
            'pending_request' => $pendingRequest ? [
                'employee_code' => $pendingRequest->employee_code,
                'created_at' => $pendingRequest->created_at->diffForHumans(),
            ] : null,
            'telegram_user' => [
                'id' => $tgUser['id'],
                'first_name' => $tgUser['first_name'] ?? '',
                'last_name' => $tgUser['last_name'] ?? '',
                'username' => $tgUser['username'] ?? '',
            ],
        ]);
    }

    /**
     * Submit an employee link request (Option B: Requires Help Desk approval).
     */
    public function requestLink(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'init_data' => 'nullable|string',
            'telegram_id' => 'required|numeric',
            'telegram_username' => 'nullable|string|max:100',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'employee_code' => 'required|string|max:50',
        ]);

        // Validate initData if in production
        if (!app()->environment('local') && !empty($validated['init_data'])) {
            $tgUser = $this->telegramAuth->validateInitData($validated['init_data']);
            if (!$tgUser || (int) $tgUser['id'] !== (int) $validated['telegram_id']) {
                return response()->json(['error' => 'Cryptographic verification failed'], 401);
            }
        }

        // Check if employee code exists in pre-approved roster
        $employee = Employee::where('employee_code', trim($validated['employee_code']))
            ->where('is_active', true)
            ->first();

        if (!$employee) {
            return response()->json([
                'error' => 'Employee ID not found in approved company roster. Please contact HR or Help Desk.',
            ], 422);
        }

        // Check if this employee ID is already linked to another telegram account
        $existingLinkedUser = User::where('employee_id', $employee->id)
            ->whereNotNull('telegram_id')
            ->first();

        if ($existingLinkedUser && $existingLinkedUser->telegram_id != $validated['telegram_id']) {
            return response()->json([
                'error' => 'This Employee ID is already bound to another Telegram account. Contact Help Desk for account recovery.',
            ], 422);
        }

        // Create or update link request
        $linkRequest = TelegramLinkRequest::updateOrCreate(
            ['telegram_id' => $validated['telegram_id']],
            [
                'telegram_username' => $validated['telegram_username'] ?? null,
                'first_name' => $validated['first_name'] ?? null,
                'last_name' => $validated['last_name'] ?? null,
                'employee_code' => $employee->employee_code,
                'status' => 'pending',
                'reviewed_by_id' => null,
                'reviewed_at' => null,
                'rejection_reason' => null,
            ]
        );

        return response()->json([
            'status' => 'submitted',
            'message' => 'Link request submitted! The Help Desk will review and activate your account shortly.',
            'employee_name' => $employee->full_name,
            'employee_code' => $employee->employee_code,
        ]);
    }

    /**
     * Web Login fallback for employees (when not using Telegram or Telegram is down).
     */
    public function webLogin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => 'required|string',
            'password' => 'required|string',
            'remember' => 'nullable|boolean',
        ]);

        $login = trim($validated['login']);
        $password = $validated['password'];

        // Find user by email, employee_code, or telegram_username
        $user = User::where('email', $login)
            ->orWhere('telegram_username', ltrim($login, '@'))
            ->orWhereHas('employee', fn($q) => $q->where('employee_code', strtoupper($login)))
            ->with(['employee.branch', 'employee.department'])
            ->first();

        if (!$user) {
            return response()->json([
                'error' => 'No employee account found matching this Email, ID, or Username.',
            ], 401);
        }

        if (!$user->is_active) {
            return response()->json([
                'error' => 'This account has been deactivated. Please contact IT Help Desk.',
            ], 403);
        }

        // Verify password
        $passwordValid = false;
        if ($user->password && Hash::check($password, $user->password)) {
            $passwordValid = true;
        } elseif (!$user->password && $password === 'Password123!') {
            // Initialize unhashed password for seeded TMA users
            $user->update(['password' => Hash::make($password)]);
            $passwordValid = true;
        }

        if (!$passwordValid) {
            return response()->json([
                'error' => 'Incorrect password. (Default corporate password: Password123!)',
            ], 401);
        }

        Auth::login($user, (bool) ($validated['remember'] ?? true));

        return response()->json([
            'status' => 'authenticated',
            'login_method' => 'password',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'telegram_id' => $user->telegram_id,
                'telegram_username' => $user->telegram_username,
                'employee_code' => $user->employee->employee_code ?? 'EMP',
                'branch' => $user->employee->branch->name ?? 'Headquarters',
                'department' => $user->employee->department->name ?? 'General',
            ],
        ]);
    }

    /**
     * Logout from TMA session.
     */
    public function logout(): JsonResponse
    {
        Auth::logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return response()->json(['status' => 'logged_out']);
    }
}
