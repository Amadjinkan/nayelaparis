<?php

namespace App\Http\Controllers;

use App\Services\EmailNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(private EmailNotificationService $emails) {}

    /**
     * POST /api/contact
     * Envoie une demande client vers l'adresse de contact administrable.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:120',
            'email' => 'required|email|max:191',
            'telephone' => 'nullable|string|max:40',
            'sujet' => 'nullable|string|max:160',
            'message' => 'required|string|min:10|max:3000',
        ]);

        if (!$this->emails->contactMessage($data)) {
            return response()->json([
                'message' => "Impossible d'envoyer le message pour le moment.",
            ], 503);
        }

        return response()->json([
            'message' => 'Votre message a bien été envoyé.',
        ]);
    }
}
