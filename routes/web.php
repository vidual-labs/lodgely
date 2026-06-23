<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Imports\GoogleSheetsImportController;
use App\Http\Controllers\Imports\MetaLeadsImportController;
use App\Http\Controllers\InboxColumnPickerController;
use App\Http\Controllers\InboxSavedFilterController;
use App\Http\Controllers\LeadExportController;
use App\Http\Controllers\OAuth\GoogleAdsOAuthController;
use App\Http\Controllers\ReportingDataController;
use App\Http\Controllers\OAuth\GoogleSheetsOAuthController;
use App\Livewire\Imports\GoogleSheetsImportPage;
use App\Livewire\Imports\MetaLeadsImportPage;
use App\Livewire\Settings\GoogleSheetsSettingsPage;
use App\Livewire\Ai\DraftsPage;
use App\Livewire\Inbox\InboxPage;
use App\Livewire\Imports\CsvImportPage;
use App\Livewire\Imports\EmailImapImportPage;
use App\Livewire\Imports\EmailMockImportPage;
use App\Livewire\Reporting\MyReportsPage;
use App\Livewire\Reporting\ReportEmailsPage;
use App\Livewire\Reporting\ReportingPage;
use App\Livewire\Reporting\ReportingViewsPage;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BackupDownloadController;
use App\Livewire\Settings\AdPlatformsPage;
use App\Livewire\Settings\AiSettingsPage;
use App\Livewire\Settings\BackupsPage;
use App\Livewire\Settings\DemoDataPage;
use App\Livewire\Settings\MailSettingsPage;
use App\Livewire\Settings\ProfilePage;
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
    Route::post('/login', [LoginController::class, 'authenticate'])->middleware('throttle:5,1')->name('login.attempt');

    Route::get('/forgot-password',          [PasswordResetController::class, 'requestForm'])->name('password.request');
    Route::post('/forgot-password',         [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}',   [PasswordResetController::class, 'resetForm'])->name('password.reset');
    Route::post('/reset-password',          [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/profile', ProfilePage::class)->name('profile');

    Route::get('/inbox',                       InboxPage::class)->name('inbox');
    Route::get('/inbox/export',                LeadExportController::class)->name('inbox.export');
    Route::post('/inbox/columns',              [InboxColumnPickerController::class, 'update'])->name('inbox.columns.update');
    Route::post('/inbox/saved-filters',        [InboxSavedFilterController::class, 'store'])->name('inbox.saved-filters.store');
    Route::post('/inbox/saved-filters/{filter}', [InboxSavedFilterController::class, 'action'])->name('inbox.saved-filters.action');
    Route::get('/imports/csv',        CsvImportPage::class)->name('imports.csv');
    Route::get('/imports/email',      EmailMockImportPage::class)->name('imports.email');
    Route::get('/imports/email-imap',    EmailImapImportPage::class)->name('imports.email-imap');
    Route::get('/imports/google-sheets', GoogleSheetsImportPage::class)->name('imports.google-sheets');
    Route::post('/imports/google-sheets/sources/{source}/fetch', [GoogleSheetsImportController::class, 'fetch'])->name('imports.google-sheets.fetch');
    Route::post('/imports/google-sheets/imports', [GoogleSheetsImportController::class, 'destroyAll'])->name('imports.google-sheets.imports.destroy-all');
    Route::post('/imports/google-sheets/imports/{import}', [GoogleSheetsImportController::class, 'destroy'])->name('imports.google-sheets.imports.destroy');
    Route::get('/imports/meta-leads', MetaLeadsImportPage::class)->name('imports.meta-leads');
    Route::post('/imports/meta-leads/imports', [MetaLeadsImportController::class, 'destroyAll'])->name('imports.meta-leads.imports.destroy-all');
    Route::post('/imports/meta-leads/imports/{import}', [MetaLeadsImportController::class, 'destroy'])->name('imports.meta-leads.imports.destroy');
    Route::get('/users',           UsersPage::class)->name('users');
    Route::get('/webhooks',        WebhooksPage::class)->name('webhooks');
    Route::get('/reporting',       ReportingPage::class)->name('reporting');
    Route::post('/reporting/ad-metrics/fetch', [ReportingDataController::class, 'fetch'])->name('reporting.ad-metrics.fetch');
    Route::post('/reporting/ad-metrics/purge', [ReportingDataController::class, 'purge'])->name('reporting.ad-metrics.purge');
    Route::get('/reporting/views',  ReportingViewsPage::class)->name('reporting.views');
    Route::get('/reporting/emails', ReportEmailsPage::class)->name('reporting.emails');
    Route::get('/my-reports',       MyReportsPage::class)->name('my-reports');

    Route::middleware('ai.enabled')->group(function () {
        Route::get('/settings/ai', AiSettingsPage::class)->name('settings.ai');
        Route::get('/ai/drafts',   DraftsPage::class)->name('ai.drafts');
    });

    Route::get('/settings/google-sheets', GoogleSheetsSettingsPage::class)->name('settings.google-sheets');

    // Ad platform (Meta / Google Ads) credentials + Google Ads OAuth handshake.
    // Operator-only enforcement lives in the page/controller.
    Route::get('/settings/ad-platforms', AdPlatformsPage::class)->name('settings.ad-platforms');
    Route::get('/settings/ad-platforms/google/connect',  [GoogleAdsOAuthController::class, 'connect'])->name('settings.ad-platforms.google.connect');
    Route::get('/settings/ad-platforms/google/callback', [GoogleAdsOAuthController::class, 'callback'])->name('settings.ad-platforms.google.callback');

    Route::get('/settings/demo-data', DemoDataPage::class)->name('settings.demo-data');

    // Outbound mail (SMTP) configuration. Operator-only enforcement lives in
    // the page; the saved row overrides the .env MAIL_* config at runtime.
    Route::get('/settings/mail', MailSettingsPage::class)->name('settings.mail');

    // The backup page renders via Livewire, but every mutation (create,
    // delete, restore) posts to a native controller and redirects back —
    // the Livewire file-upload + wire:submit path silently dropped in
    // production (see BackupController / CLAUDE.md).
    Route::get('/settings/backups',          BackupsPage::class)->name('settings.backups');
    Route::post('/settings/backups/create',  [BackupController::class, 'create'])->name('settings.backups.create');
    Route::post('/settings/backups/delete',  [BackupController::class, 'destroy'])->name('settings.backups.delete');
    Route::post('/settings/backups/restore', [BackupController::class, 'restore'])->name('settings.backups.restore');
    Route::get('/settings/backups/{filename}/download', BackupDownloadController::class)->name('settings.backups.download');

    // Google Sheets OAuth handshake. Operator-only enforcement lives in the
    // controller; the callback URL must match the redirect URI configured on
    // the OAuth client in Google Cloud Console.
    Route::get('/settings/google-sheets/connect',  [GoogleSheetsOAuthController::class, 'connect'])->name('settings.google-sheets.connect');
    Route::get('/settings/google-sheets/callback', [GoogleSheetsOAuthController::class, 'callback'])->name('settings.google-sheets.callback');
});
