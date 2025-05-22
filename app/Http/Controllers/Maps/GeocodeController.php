<?php

namespace App\Http\Controllers\Maps;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GeocodeController extends Controller
{
    public function geocode(Request $request)
    {
        $request->validate([
            'q' => 'required|string|min:3',
        ]);

        // Usar caché para respetar el límite de Nominatim (1 req/s)
        $cacheKey = 'geocode_' . md5($request->q);

        $results = Cache::remember($cacheKey, 3600, function () use ($request) {

            $response = Http::withHeaders([
                'User-Agent' => 'MyApp/1.0 (gaboripin@gmail.com)', // ¡Cambia esto por tu email!
            ])->get('https://nominatim.openstreetmap.org/search', [
                'q' => $request->q,
                'format' => 'json',
                'limit' => 5,
                'addressdetails' => 1,
            ]);

            Log::info('Nominatim geocode request', [
                'query' => $request->q,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            if ($response->successful()) {
                return ['data' => $response->json()];
            }

            return ['error' => 'No se pudo contactar con Nominatim', 'status' => $response->status()];
        });

        if (isset($results['data']) && !empty($results['data'])) {
            return response()->json($results);
        }

        return response()->json([
            'message' => $results['error'] ?? 'No se encontraron resultados para la búsqueda',
            'status' => $results['status'] ?? 404,
        ], $results['status'] ?? 404);
    }

    public function reverseGeocode(Request $request)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $cacheKey = 'reverse_geocode_' . md5($request->lat . '_' . $request->lng);
        $results = Cache::remember($cacheKey, 3600, function () use ($request) {
            $response = Http::withHeaders([
                'User-Agent' => 'MyApp/1.0 (gaboripin@gmail.com)', // ¡Cambia esto por tu email!
            ])->get('https://nominatim.openstreetmap.org/reverse', [
                'lat' => $request->lat,
                'lon' => $request->lng,
                'format' => 'json',
                'addressdetails' => 1,
            ]);

            if ($response->successful()) {
                return ['data' => $response->json()];
            }

            return ['error' => 'No se pudo contactar con Nominatim', 'status' => $response->status()];
        });

        if (isset($results['data']) && !empty($results['data'])) {
            return response()->json($results);
        }

        return response()->json([
            'message' => $results['error'] ?? 'No se encontraron resultados para la búsqueda',
            'status' => $results['status'] ?? 404,
        ], $results['status'] ?? 404);
    }
}