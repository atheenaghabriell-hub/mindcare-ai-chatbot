<?php

use App\Http\Controllers\AdminAnalyticsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MoodController;
use App\Http\Controllers\PrivacySettingsController;
use App\Http\Controllers\SafetyReviewController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => Auth::check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login.store');
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'loginAdmin'])
        ->middleware('throttle:admin-login')
        ->name('admin.login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/conversations', [ConversationController::class, 'index'])->name('conversations.index');
    Route::post('/conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::get('/conversations/{conversation}', [ConversationController::class, 'show'])->name('conversations.show');
    Route::delete('/conversations/{conversation}', [ConversationController::class, 'destroy'])->name('conversations.destroy');
    Route::post('/conversations/{conversation}/messages', [MessageController::class, 'store'])
        ->middleware('throttle:chat')
        ->name('messages.store');

    Route::post('/moods', [MoodController::class, 'store'])->name('moods.store');
    Route::get('/settings/privacy', [PrivacySettingsController::class, 'edit'])->name('settings.privacy');
    Route::put('/settings/privacy', [PrivacySettingsController::class, 'update'])->name('settings.privacy.update');
    Route::delete('/settings/account', [PrivacySettingsController::class, 'destroyAccount'])->name('settings.account.destroy');

    Route::get('/admin/analytics', AdminAnalyticsController::class)->name('admin.analytics');
    Route::get('/admin/safety-reviews', [SafetyReviewController::class, 'index'])->name('admin.safety-reviews');
});
