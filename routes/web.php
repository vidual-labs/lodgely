<?php

use App\Http\Controllers\Auth\LoginController;
use App\Livewire\Inbox\InboxPage;
use App\Livewire\Imports\CsvImportPage;
use App\Livewire\Imports\EmailMockImportPage;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/inbox');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'authenticate'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/inbox',           InboxPage::class)->name('inbox');
    Route::get('/imports/csv',     CsvImportPage::class)->name('imports.csv');
    Route::get('/imports/email',   EmailMockImportPage::class)->name('imports.email');
});
