<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// This route will manually log in user with ID 1
Route::get('/test-login', function () {
    \Illuminate\Support\Facades\Auth::loginUsingId(1); // Make sure you have a user with ID 1
    return $user = \Illuminate\Support\Facades\Auth::user();
});

// This route is protected by the standard web 'auth' middleware
Route::get('/test-auth-check', function () {
    return \Illuminate\Support\Facades\Auth::user();
})->middleware('auth');
