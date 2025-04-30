<?php

namespace App\Http\Controllers;

use Exception;
use Stripe\Stripe;
use App\Models\User;
use Stripe\Customer;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /** 
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        try {
            $validatedAttributes = $request->validate([
                'nombre' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'telefono' => 'nullable|string|max:20',
                'direccion' => 'nullable|string|max:255',
                'profile_photo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'password' => ['required', Password::min(8)->mixedCase()->numbers()->symbols(), 'confirmed'],
            ]);

            $profilePhotoPath = null;
            if ($request->hasFile('profile_photo') && $request->file('profile_photo')->isValid()) {
                $profilePhotoPath = $request->file('profile_photo')->store('profile_photos', 'public');
            }
            
            $stripeCustomer = StripeController::crearCliente(
                $validatedAttributes['email'],
                $validatedAttributes['nombre']
            );

            $user = User::create([
                'nombre' => $validatedAttributes['nombre'],
                'email' => $validatedAttributes['email'],
                'telefono' => $validatedAttributes['telefono'],
                'direccion' => $validatedAttributes['direccion'],
                'profile_photo' => $profilePhotoPath,
                'password' => bcrypt($validatedAttributes['password']),
                'rol' => 'client',
            ]);

            Client::create([
                'user_id' => $user->id,
                'stripe_customer_id' => $stripeCustomer->id,
                'rating' => 0.00,
                'cantidad_ratings' => 0,
            ]);

            $user->load('client');
    
            return response()->json([
                'data' => $user,
                'message' => 'Usuario registrado exitosamente',
                'status' => 201,
            ], 201);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al registrar el usuario: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }

    }    
}
