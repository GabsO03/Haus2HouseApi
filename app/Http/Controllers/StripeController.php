<?php

namespace App\Http\Controllers;

use Exception;
use Stripe\Customer;
use App\Models\Client;
use App\Models\Service;
use Stripe\StripeClient;
use Illuminate\Http\Request;

class StripeController extends Controller
{
    protected static $stripe;

    public static function initialize()
    {
        if (!self::$stripe) {
            self::$stripe = new StripeClient(env('STRIPE_SECRET'));
        }
    }

    /**
     * Crear un cliente en Stripe
     */
    public static function crearCliente(string $email, string $name): Customer
    {
        self::initialize();
        $stripeCustomer = self::$stripe->customers->create([
            'email' => $email,
            'name' => $name,
        ]);

        return $stripeCustomer;
    }

    /**
     * Añadir el método de pago del cliente
     */
    public static function aniadirMetodoPago(Request $request, string $id)
    {
        try {
            self::initialize();
            $client = Client::findOrFail($id);

            if (!$client->stripe_customer_id) {
                throw new Exception('Cliente sin cuenta de Stripe configurada');
            }

            // Validar el token de tarjeta (en lugar de datos crudos)
            $validated = $request->validate([
                'card_token' => 'required|string', // Usamos un token de prueba de Stripe
            ]);

            // Crear el método de pago con el token
            $paymentMethod = self::$stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'token' => $validated['card_token'], // Token de prueba, como tok_visa
                ],
            ]);

            // Asociar el método de pago al cliente
            self::$stripe->paymentMethods->attach(
                $paymentMethod->id, // Usamos el ID del método de pago creado
                ['customer' => $client->stripe_customer_id]
            );

            // Establecer como método de pago predeterminado
            self::$stripe->customers->update($client->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethod->id, // Usamos el ID correcto
                ],
            ]);

            return response()->json([
                'message' => 'Método de pago añadido',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al añadir método de pago: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    public static function obtenerMetodosPago(Request $request, string $id)
    {
        try {
            self::initialize();
            $client = Client::findOrFail($id);

            if (!$client->stripe_customer_id) {
                throw new Exception('Cliente sin cuenta de Stripe configurada');
            }

            $paymentMethods = self::$stripe->paymentMethods->list([
                'customer' => $client->stripe_customer_id,
                'type' => 'card',
            ]);

            return response()->json([
                'data' => $paymentMethods->data,
                'status' => 200,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener métodos de pago: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Procesa el pago con Stripe
     */
    public static function procesarPago(Service $service): void
    {
        self::initialize();
        $client = $service->client;
        if (!$client->stripe_customer_id) {
            throw new Exception('Cliente sin cuenta de Stripe configurada');
        }

        $customer = self::$stripe->customers->retrieve($client->stripe_customer_id);
        if (!$customer->invoice_settings->default_payment_method) {
            throw new Exception('Cliente sin método de pago configurado');
        }

        $amountInCents = (int) ($service->total_amount * 100); // Convierto a centavos

        $paymentIntent = self::$stripe->paymentIntents->create([
            'amount' => $amountInCents,
            'currency' => 'eur',
            'customer' => $client->stripe_customer_id,
            'payment_method' => $customer->invoice_settings->default_payment_method,
            'confirm' => true,
            'off_session' => true,
        ]);

        // Esto por si la tarjeta no tiene fondos
        if ($paymentIntent->status !== 'succeeded') {
            throw new Exception('El pago falló: ' . ($paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Razón desconocida'));
        }

        $service->update([
            'payment_stripe_id' => $paymentIntent->id,
        ]);
    }

    
    public static function reembolsar(Service $service): void
    {
        self::initialize();
        if (!$service->payment_stripe_id) {
            throw new Exception('No hay pago para reembolsar');
        }

        self::$stripe->refunds->create([
            'payment_intent' => $service->payment_stripe_id,
        ]);
    }
    
}