<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    /**
     * GET /api/config
     * Retourne la configuration publique nécessaire au frontend.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'app_name' => config('app.name'),
            'stripe_public_key' => config('services.stripe.key'),
            'currency' => strtoupper(config('services.stripe.currency', 'cad')),
            'frontend_url' => config('app.url'),
        ]);
    }
}
