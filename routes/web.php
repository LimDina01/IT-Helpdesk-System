<?php

use App\Http\Controllers\Portal\PortalAdminController;
use App\Http\Controllers\Portal\PortalAuthController;
use App\Http\Controllers\Portal\PortalCockpitController;
use App\Http\Controllers\Portal\PortalDashboardController;
use App\Http\Controllers\Portal\PortalLinkRequestController;
use App\Http\Controllers\Portal\PortalTicketController;
use App\Http\Controllers\Tma\TmaAuthController;
use App\Http\Controllers\Tma\TmaTicketController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('tma.index');
});

/*
|--------------------------------------------------------------------------
| Telegram Mini App (TMA) Requester Portal Routes
|--------------------------------------------------------------------------
*/

// TMA Main Container View
Route::get('/tma', function () {
    return response()
        ->view('tma.app')
        ->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0')
        ->header('Pragma', 'no-cache')
        ->header('Expires', '0');
})->name('tma.index');

// TMA API
Route::prefix('tma/api')->group(function () {
    Route::post('/auth', [TmaAuthController::class, 'authenticate'])->name('tma.auth');
    Route::post('/login', [TmaAuthController::class, 'webLogin'])->name('tma.web-login');
    Route::post('/link-request', [TmaAuthController::class, 'requestLink'])->name('tma.link-request');
    Route::post('/logout', [TmaAuthController::class, 'logout'])->name('tma.logout');
    Route::get('/form-data', [TmaTicketController::class, 'getFormData'])->name('tma.form-data');

    // Authenticated Requester Endpoints
    Route::middleware('auth')->group(function () {
        Route::get('/tickets', [TmaTicketController::class, 'index'])->name('tma.tickets.index');
        Route::post('/tickets', [TmaTicketController::class, 'store'])->name('tma.tickets.store');
        Route::get('/tickets/{ticket}', [TmaTicketController::class, 'show'])->name('tma.tickets.show');
        Route::post('/tickets/{ticket}/leave', [TmaTicketController::class, 'leaveChat'])->name('tma.tickets.leave');
        Route::post('/tickets/{ticket}/messages', [TmaTicketController::class, 'addMessage'])->name('tma.tickets.messages.store');
        Route::post('/tickets/{ticket}/confirm', [TmaTicketController::class, 'confirmResolution'])->name('tma.tickets.confirm');
        Route::post('/tickets/{ticket}/reopen', [TmaTicketController::class, 'reopenTicket'])->name('tma.tickets.reopen');
        Route::post('/approvals/{approval}/approve', [TmaTicketController::class, 'approveStep'])->name('tma.approvals.approve');
        Route::post('/approvals/{approval}/reject', [TmaTicketController::class, 'rejectStep'])->name('tma.approvals.reject');
    });
});

// Authorized Private Attachment Download for TMA
Route::get('/tma/tickets/{ticket}/attachments/{attachment}/download', [TmaTicketController::class, 'downloadAttachment'])
    ->middleware('auth')
    ->name('tma.attachments.download');

/*
|--------------------------------------------------------------------------
| Operations & Management Web Portal (Help Desk, Technicians, HOD, Admin)
|--------------------------------------------------------------------------
*/

Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('login');

Route::prefix('portal')->group(function () {
    // Guest Authentication
    Route::get('/login', [PortalAuthController::class, 'showLogin'])->name('portal.login');
    Route::post('/login', [PortalAuthController::class, 'login'])->name('portal.login.submit');
    Route::post('/logout', [PortalAuthController::class, 'logout'])->name('portal.logout');

    // Protected Staff Routes
    Route::middleware(['auth', 'staff'])->group(function () {
        Route::get('/', [PortalDashboardController::class, 'index'])->name('portal.dashboard');

        // Ticket Management
        Route::get('/tickets/{ticket}', [PortalTicketController::class, 'show'])->name('portal.tickets.show');
        Route::get('/tickets/{ticket}/poll', [PortalTicketController::class, 'poll'])->name('portal.tickets.poll');
        Route::post('/tickets/{ticket}/assign', [PortalTicketController::class, 'assign'])->name('portal.tickets.assign');
        Route::post('/tickets/{ticket}/accept', [PortalTicketController::class, 'acceptAndStart'])->name('portal.tickets.accept');
        Route::post('/tickets/{ticket}/clarify', [PortalTicketController::class, 'requestClarification'])->name('portal.tickets.clarify');
        Route::post('/tickets/{ticket}/done', [PortalTicketController::class, 'markDone'])->name('portal.tickets.done');
        Route::post('/tickets/{ticket}/block', [PortalTicketController::class, 'markBlocked'])->name('portal.tickets.block');
        Route::post('/tickets/{ticket}/messages', [PortalTicketController::class, 'addMessage'])->name('portal.tickets.messages.store');
        Route::get('/tickets/{ticket}/attachments/{attachment}/download', [PortalTicketController::class, 'downloadAttachment'])->name('portal.attachments.download');
        Route::post('/tickets/{ticket}/approvals/{approval}/approve', [PortalTicketController::class, 'approveStep'])->name('portal.tickets.approvals.approve');
        Route::post('/tickets/{ticket}/approvals/{approval}/reject', [PortalTicketController::class, 'rejectStep'])->name('portal.tickets.approvals.reject');

        // Option B: Telegram Account Link Requests
        Route::get('/link-requests', [PortalLinkRequestController::class, 'index'])->name('portal.link-requests.index');
        Route::post('/link-requests/{linkRequest}/approve', [PortalLinkRequestController::class, 'approve'])->name('portal.link-requests.approve');
        Route::post('/link-requests/{linkRequest}/reject', [PortalLinkRequestController::class, 'reject'])->name('portal.link-requests.reject');

        // Sprint 5: HOD Executive Operations Cockpit
        Route::get('/cockpit', [PortalCockpitController::class, 'index'])->name('portal.cockpit');
        Route::post('/cockpit/override-priority/{ticket}', [PortalCockpitController::class, 'overridePriority'])->name('portal.cockpit.override-priority');

        // Sprint 5: System Administration Workspace
        Route::prefix('admin')->group(function () {
            Route::get('/', [PortalAdminController::class, 'index'])->name('portal.admin.index');
            Route::post('/categories', [PortalAdminController::class, 'storeCategory'])->name('portal.admin.categories.store');
            Route::put('/categories/{category}', [PortalAdminController::class, 'updateCategory'])->name('portal.admin.categories.update');
            Route::post('/categories/{category}/toggle', [PortalAdminController::class, 'toggleCategory'])->name('portal.admin.categories.toggle');

            Route::get('/holidays', [PortalAdminController::class, 'holidays'])->name('portal.admin.holidays');
            Route::post('/holidays', [PortalAdminController::class, 'storeHoliday'])->name('portal.admin.holidays.store');
            Route::delete('/holidays/{holiday}', [PortalAdminController::class, 'destroyHoliday'])->name('portal.admin.holidays.destroy');

            Route::get('/users', [PortalAdminController::class, 'users'])->name('portal.admin.users');
            Route::post('/users/{user}/roles', [PortalAdminController::class, 'updateUserRoles'])->name('portal.admin.users.roles');
            Route::post('/users/{user}/toggle', [PortalAdminController::class, 'toggleUserActive'])->name('portal.admin.users.toggle');
        });
    });
});

