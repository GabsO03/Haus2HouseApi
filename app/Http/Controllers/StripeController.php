<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use Stripe\Customer;
use App\Models\Client;
use App\Models\Service;
use Stripe\StripeClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StripeController extends Controller
{
    protected static $stripe;

    public static function initialize()
    {
        if (!self::$stripe) {
            self::$stripe = new StripeClient([
                'api_key' => env('STRIPE_SECRET'),
                'stripe_version' => '2023-10-16'
            ]);
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
            $user = User::with('client')->where('id', $id)->firstOrFail();

            // Obtener el cliente relacionado
            $client = $user->client;
            if (!$client) {
                throw new Exception('Client not found for user ID: ' . $id);
            }

            if (!$client->stripe_customer_id) {
                $stripeCustomer = StripeController::crearCliente(
                    $user->email,
                    $user->nombre
                );
                $client->update([
                    'stripe_customer_id' => $stripeCustomer->id
                ]);
            }

            // Validar el token de tarjeta (en lugar de datos crudos)
            $validated = $request->validate([
                'card_token' => 'required|string', // Usamos un token de prueba de Stripe
            ]);


            // Crear el método de pago con el token
            $paymentMethod = self::$stripe->paymentMethods->create([
                'type' => 'card',
                'card' => [
                    'token' => $validated['card_token']
                ],
            ]);

            // Asociar el método de pago al cliente
            self::$stripe->paymentMethods->attach(
                $paymentMethod->id,
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
            Log::error('Error al añadir método de pago', [
                'user_id' => $id,
                'error_message' => $e->getMessage(),
                'card_token' => $request->input('card_token', 'no definido'),
                'stripe_customer_id' => $client->stripe_customer_id ?? 'no definido',
            ]);
            return response()->json([
                'message' => 'Error al añadir método de pago: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    public static function obtenerMetodosPago(string $id)
    {
        try {
            self::initialize();
            $user = User::with('client')->where('id', $id)->firstOrFail();
            $client = $user->client;

            if (!$client) {
                throw new Exception('Client not found for user ID: ' . $id);
            }

            $metodos_pago = [];

            if ($client->stripe_customer_id) {
                $paymentMethods = self::$stripe->paymentMethods->all([
                    'customer' => $client->stripe_customer_id,
                    'type' => 'card',
                ]);

                $metodos_pago = $paymentMethods->data;
            }

            return response()->json([
                'data' => $metodos_pago,
                'status' => 200,
            ]);

        } catch (Exception $e) {
            return response()->json([
                'message' => 'Error al obtener métodos de pago: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    public static function eliminarMetodoPago(string $id)
    {
        try {
            Log::info('ID recibido para eliminar método de pago: ' . $id);
            self::initialize();

            // Buscar el usuario con su cliente
            $user = User::with('client')->where('id', $id)->firstOrFail();
            Log::info('Usuario encontrado: ' . $user->id);

            $client = $user->client;
            if (!$client) {
                throw new Exception('Client not found for user ID: ' . $id);
            }
            Log::info('Cliente encontrado: ' . $client->id);

            if (!$client->stripe_customer_id) {
                throw new Exception('Cliente sin cuenta de Stripe configurada');
            }

            // Obtener los métodos de pago actuales
            $paymentMethods = self::$stripe->paymentMethods->all([
                'customer' => $client->stripe_customer_id,
                'type' => 'card',
            ]);

            if (empty($paymentMethods->data)) {
                throw new Exception('No hay método de pago para eliminar');
            }

            // Eliminar el método de pago
            foreach ($paymentMethods->data as $method) {
                self::$stripe->paymentMethods->detach($method->id);
                Log::info('Método de pago eliminado: ' . $method->id);
            }

            // Opcional: Limpiar el método de pago predeterminado
            self::$stripe->customers->update($client->stripe_customer_id, [
                'invoice_settings' => [
                    'default_payment_method' => null,
                ],
            ]);

            $client->update([
                'stripe_customer_id' => null
            ]);

            return response()->json([
                'message' => 'Método de pago eliminado',
                'status' => 200,
            ]);
        } catch (Exception $e) {
            Log::error('Error al eliminar método de pago: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar método de pago: ' . $e->getMessage(),
                'status' => 500,
            ], 500);
        }
    }

    /**
     * Procesa el pago con Stripe
     */
    public static function procesarPago(Service $service): void {
        try {
            Log::info('Iniciando procesamiento de pago', ['service_id' => $service->id]);

            self::initialize();
            Log::info('Stripe inicializado');

            $client = $service->client;
            if (!$client->stripe_customer_id) {
                Log::error('Cliente sin cuenta de Stripe configurada', ['client_id' => $client->id]);
                throw new Exception('Cliente sin cuenta de Stripe configurada');
            }
            Log::info('Cliente recuperado', ['client_id' => $client->id, 'stripe_customer_id' => $client->stripe_customer_id]);

            $customer = self::$stripe->customers->retrieve($client->stripe_customer_id);
            if (!$customer->invoice_settings->default_payment_method) {
                Log::error('Cliente sin método de pago configurado', ['client_id' => $client->id, 'stripe_customer_id' => $client->stripe_customer_id]);
                throw new Exception('Cliente sin método de pago configurado');
            }
            Log::info('Método de pago verificado', ['payment_method' => $customer->invoice_settings->default_payment_method]);

            $amountInCents = (int) ($service->total_amount * 100); // Convierto a centavos
            Log::info('Monto calculado', ['amount_in_cents' => $amountInCents, 'currency' => 'eur']);

            $paymentIntent = self::$stripe->paymentIntents->create([
                'amount' => $amountInCents,
                'currency' => 'eur',
                'customer' => $client->stripe_customer_id,
                'payment_method' => $customer->invoice_settings->default_payment_method,
                'confirm' => true,
                'off_session' => true,
            ]);
            Log::info('PaymentIntent creado', ['payment_intent_id' => $paymentIntent->id, 'status' => $paymentIntent->status]);

            // Esto por si la tarjeta no tiene fondos
            if ($paymentIntent->status !== 'succeeded') {
                Log::error('El pago falló', [
                    'payment_intent_id' => $paymentIntent->id,
                    'error_message' => $paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Razón desconocida'
                ]);
                throw new Exception('El pago falló: ' . ($paymentIntent->last_payment_error ? $paymentIntent->last_payment_error->message : 'Razón desconocida'));
            }

            $service->update([
                'payment_stripe_id' => $paymentIntent->id,
            ]);
            Log::info('Servicio actualizado con PaymentIntent', ['service_id' => $service->id, 'payment_stripe_id' => $paymentIntent->id]);

        } catch (Exception $e) {
            Log::error('Error al procesar el pago', [
                'service_id' => $service->id,
                'error_message' => $e->getMessage(),
                'client_id' => isset($client) ? $client->id : 'no definido'
            ]);
            throw $e; // Re-lanzamos la excepción para que el controlador que llama maneje el error
        }
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