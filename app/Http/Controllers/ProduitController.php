<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProduitController extends Controller
{
    /**
     * GET /api/produits
     * Catalogue public — tous les produits actifs.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Produit::actif();

        if ($request->filled('categorie')) {
            $query->where('categorie', $request->categorie);
        }
        if ($request->boolean('featured')) {
            $query->featured();
        }

        $produits = $query->orderBy('nom')->get();

        return response()->json($produits);
    }

    /**
     * GET /api/admin/produits
     * Catalogue complet pour l'administration, y compris les articles masqués.
     */
    public function adminIndex(): JsonResponse
    {
        return response()->json(Produit::orderBy('nom')->get());
    }

    /**
     * GET /api/produits/{id}
     */
    public function show(int $id): JsonResponse
    {
        $produit = Produit::actif()->findOrFail($id);
        return response()->json($produit);
    }

    /**
     * POST /api/admin/produits  (admin uniquement)
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nom' => 'required|string|max:200',
            'categorie' => 'required|string|max:100',
            'prix' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'tailles' => 'nullable|string|max:200',
            'couleurs' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'emoji' => 'nullable|string|max:10',
            'image' => 'nullable|string|max:500',
            'featured' => 'nullable|boolean',
            'actif' => 'nullable|boolean',
        ]);

        $produit = Produit::create([
            'nom' => $data['nom'],
            'categorie' => $data['categorie'],
            'prix' => $data['prix'],
            'stock' => $data['stock'],
            'tailles' => $data['tailles'] ?? 'Unique',
            'couleurs' => $data['couleurs'] ?? null,
            'description' => $data['description'] ?? null,
            'emoji' => $data['emoji'] ?? '',
            'image' => $data['image'] ?? null,
            'featured' => $data['featured'] ?? false,
            'actif' => $data['actif'] ?? true,
        ]);

        return response()->json([
            'message' => 'Produit créé',
            'id' => $produit->id,
            'produit' => $produit,
        ], 201);
    }

    /**
     * PUT /api/admin/produits/{id}  (admin uniquement)
     * Aussi utilisé pour modifier rapidement le stock.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $produit = Produit::findOrFail($id);

        $data = $request->validate([
            'nom' => 'sometimes|string|max:200',
            'categorie' => 'sometimes|string|max:100',
            'prix' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|integer|min:0',
            'tailles' => 'sometimes|nullable|string|max:200',
            'couleurs' => 'sometimes|nullable|string|max:200',
            'description' => 'sometimes|nullable|string',
            'emoji' => 'sometimes|nullable|string|max:10',
            'image' => 'sometimes|nullable|string|max:500',
            'featured' => 'sometimes|boolean',
            'actif' => 'sometimes|boolean',
        ]);

        $produit->update($data);

        return response()->json([
            'message' => 'Produit mis à jour',
            'produit' => $produit,
        ]);
    }

    /**
     * DELETE /api/admin/produits/{id}  (admin uniquement)
     */
    public function destroy(int $id): JsonResponse
    {
        $produit = Produit::findOrFail($id);
        // On désactive plutôt que de supprimer (pour préserver les commandes historiques)
        $produit->update(['actif' => false]);

        return response()->json(['message' => 'Produit désactivé']);
    }
}
