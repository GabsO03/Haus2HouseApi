<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\WorkerController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\StripeController;

// Registro y login
Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [SessionController::class, 'store']);
Route::post('/logout', [SessionController::class, 'destroy']);

// Clientes
Route::get('/clients', [ClientController::class, 'index']);
Route::get('/clients/{client}', [ClientController::class, 'show']);
Route::put('/clients/{client}', [ClientController::class, 'update']);
Route::delete('/clients/{client}', [ClientController::class, 'destroy']);
Route::post('/clients/{id}/payment-method', [StripeController::class, 'aniadirMetodoPago']);

// Trabajadores
Route::get('/workers', [WorkerController::class, 'index']);
Route::post('/workers', [WorkerController::class, 'store']);
Route::get('/workers/filter', [WorkerController::class, 'filter']);
Route::get('/workers/{worker}', [WorkerController::class, 'show']);
Route::put('/workers/{worker}/profile', [WorkerController::class, 'updatePerfil']);
Route::put('/workers/{worker}/schedule', [WorkerController::class, 'updateHorario']);
Route::delete('/workers/{worker}', [WorkerController::class, 'destroy']);


// Manejo de contratos
Route::post('/services', [ServiceController::class, 'store']);
Route::get('/services/pendientes', [ServiceController::class, 'listarPendientes']);
Route::put('/services/{service}/estado', [ServiceController::class, 'actualizarEstado']);
Route::put('/services/{service}/valorar', [ServiceController::class, 'valorar']);