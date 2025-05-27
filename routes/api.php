<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\Maps\GeocodeController;


// Ubicacion
Route::get('/geocode', [GeocodeController::class, 'geocode']);
Route::get('/reverse-geocode', [GeocodeController::class, 'reverseGeocode']);

// Registro y login
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [SessionController::class, 'store']);
Route::post('/logout', [SessionController::class, 'destroy']);

//Users
Route::get('/users/{user}', [UserController::class, 'show']);
Route::post('/users/{user}/change-password-authorization', [UserController::class, 'changePasswordAuthorization']);
Route::post('/upload-profile-photo', [UserController::class, 'uploadProfilePhoto']);
Route::get('/proxy-image/{fileId}', [UserController::class, 'getProfilePhoto']);

// Clientes
Route::get('/clients', [ClientController::class, 'index']);
Route::get('/clients/{client}', [ClientController::class, 'show']);
Route::put('/clients/{client}', [ClientController::class, 'update']);
Route::delete('/clients/{client}', [ClientController::class, 'destroy']);
Route::post('/clients/{id}/payment-method', [StripeController::class, 'aniadirMetodoPago']);
Route::get('/clients/{id}/payment-methods', [StripeController::class, 'obtenerMetodosPago']);
Route::delete('/clients/{id}/payment-methods', [StripeController::class, 'eliminarMetodoPago']);

// Trabajadores
Route::get('/workers', [WorkerController::class, 'index']);
Route::post('/workers', [WorkerController::class, 'store']);
Route::get('/workers/filter', [WorkerController::class, 'filter']);
Route::get('/workers/{worker}/toggleActive', [WorkerController::class, 'toggleWorkerActivo']);
Route::get('/workers/{worker}/schedule', [WorkerController::class, 'getHorario']);
Route::put('/workers/{worker}/profile', [WorkerController::class, 'updatePerfil']);
Route::put('/workers/{worker}/schedule', [WorkerController::class, 'updateHorario']);
Route::get('/workers/{worker}', [WorkerController::class, 'show']);
Route::delete('/workers/{worker}', [WorkerController::class, 'destroy']);


// Manejo de contratos
Route::get('/services/types', [ServiceController::class, 'serviceTypes']);
Route::get('/services/{user}/pending', [ServiceController::class, 'index']);
Route::get('/services/{user}/history', [ServiceController::class, 'historial']);
Route::get('/services/{service}', [ServiceController::class, 'show']);
Route::put('/services/{service}/confirm-payment', [ServiceController::class, 'actualizarEstadoPago']);
Route::put('/services/{service}/estado', [ServiceController::class, 'actualizarEstado']);
Route::post('/services', [ServiceController::class, 'store']);
Route::put('/services/{service}/valorar', [ServiceController::class, 'valorar']);