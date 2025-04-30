<?php

use Illuminate\Support\Facades\Route;


Route::get('/', function () {
    return view('welcome');
});

use Illuminate\Support\Facades\Mail;

Route::get('/test-mail', function () {
    try {
        Mail::raw('¡Prueba desde Laravel con Gmail!', function ($message) {
            $message->to('gaboripin@gmail.com')
                    ->subject('Correo de Prueba desde Laravel');
        });
        return 'Correo enviado exitosamente';
    } catch (\Exception $e) {
        return 'Error: ' . $e->getMessage();
    }
});
