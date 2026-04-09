<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {


Route::middleware(['auth'])->group(function () {

    Route::prefix('admin')->group(function () {

        Route::get('/dashboard', function () {
            return view('admin.dashboard.index');
        })->name('dashboard');

    });

});


    return view('welcome');
});
