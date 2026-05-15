<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Inbox\InboxPage;
use App\Livewire\Imports\CsvImportPage;
use App\Livewire\Imports\EmailImapImportPage;
use App\Livewire\Imports\EmailMockImportPage;
use App\Livewire\Reporting\ReportingPage;
use App\Livewire\Users\UsersPage;
use App\Livewire\Webhooks\WebhooksPage;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inbox');

Route::post('/locale', function (Request $request) {
    $locale = $request->input('locale');
    if (in_array($locale, SetLocale::SUPPORTED, true)) {
        session(['locale' => $locale]);
        $request->user()?->update(['locale' => $locale]);
    }
    return back();
})->name('locale');

Route::post('/user/theme', function (Request $request) {
    $theme = $request->input('theme');
    if (in_array($theme, ['light', 'dark'], true)) {
        $request->user()?->update(['ui_theme' => $theme]);
    }
    return response()->noContent();
})->middleware('auth')->name('user.theme');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/inbox',           InboxPage::class)->name('inbox');
    Route::get('/imports/csv',        CsvImportPage::class)->name('imports.csv');
    Route::get('/imports/email',      EmailMockImportPage::class)->name('imports.email');
    Route::get('/imports/email-imap', EmailImapImportPage::class)->name('imports.email-imap');
    Route::get('/users',           UsersPage::class)->name('users');
    Route::get('/webhooks',        WebhooksPage::class)->name('webhooks');
    Route::get('/reporting',       ReportingPage::class)->name('reporting');
});
