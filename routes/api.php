<?php

use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Api\ImportUserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/users', function () {
    return \App\Models\User::query()->with(['primaryImage', 'images'])->get();
});

Route::post('/upload/init', [UploadController::class, 'init']);
Route::post('/upload/{upload}/chunk', [UploadController::class, 'chunk']);
Route::post('/upload/{upload}/complete', [UploadController::class, 'complete']);

Route::post('/import-csv', [ImportUserController::class, 'import']);
