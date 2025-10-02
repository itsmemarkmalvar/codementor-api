<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Add named route for login redirect
Route::get('/login', function () {
    return response()->json(['message' => 'Please use the API login endpoint'], 401);
})->name('login');
