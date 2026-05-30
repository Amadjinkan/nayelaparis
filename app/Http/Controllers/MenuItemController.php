<?php

namespace App\Http\Controllers;

use App\Models\MenuItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MenuItemController extends Controller
{
    /**
     * GET /api/menu
     * Menu public visible par les clients.
     */
    public function index(): JsonResponse
    {
        return response()->json(
            MenuItem::visible()
                ->ordered()
                ->get()
                ->map(fn (MenuItem $item) => $item->toFrontend())
        );
    }

    /**
     * GET /api/admin/menu
     * Menu complet pour l'administration.
     */
    public function adminIndex(): JsonResponse
    {
        return response()->json(
            MenuItem::ordered()
                ->get()
                ->map(fn (MenuItem $item) => $item->toFrontend())
        );
    }

    /**
     * POST /api/admin/menu
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validatedData($request);

        $item = MenuItem::create([
            'slug' => $this->uniqueSlug($data['label_fr']),
            'label_fr' => $data['label_fr'],
            'label_en' => $data['label_en'] ?? $data['label_fr'],
            'type' => $data['type'],
            'page_key' => $data['type'] === 'page' ? $data['page_key'] : null,
            'url' => $data['type'] === 'url' ? $data['url'] : null,
            'position' => (MenuItem::max('position') ?? 0) + 1,
            'is_active' => $data['is_active'] ?? true,
            'is_locked' => false,
        ]);

        return response()->json([
            'message' => 'Onglet cree',
            'item' => $item->toFrontend(),
        ], 201);
    }

    /**
     * PUT /api/admin/menu/{id}
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);
        $data = $this->validatedData($request, partial: true);

        $type = $data['type'] ?? $item->type;
        $pageKey = $type === 'page' ? ($data['page_key'] ?? $item->page_key) : null;
        $url = $type === 'url' ? ($data['url'] ?? $item->url) : null;

        if ($type === 'page' && empty($pageKey)) {
            throw ValidationException::withMessages([
                'page_key' => 'La page interne est obligatoire.',
            ]);
        }

        if ($type === 'url' && empty($url)) {
            throw ValidationException::withMessages([
                'url' => 'L URL externe est obligatoire.',
            ]);
        }

        $item->update([
            'label_fr' => $data['label_fr'] ?? $item->label_fr,
            'label_en' => $data['label_en'] ?? $item->label_en,
            'type' => $type,
            'page_key' => $pageKey,
            'url' => $url,
            'is_active' => $data['is_active'] ?? $item->is_active,
        ]);

        return response()->json([
            'message' => 'Onglet mis a jour',
            'item' => $item->fresh()->toFrontend(),
        ]);
    }

    /**
     * DELETE /api/admin/menu/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $item = MenuItem::findOrFail($id);

        if ($item->is_locked) {
            return response()->json([
                'message' => 'Cet onglet est protege et ne peut pas etre supprime.',
            ], 422);
        }

        $item->delete();
        $this->normalizePositions();

        return response()->json(['message' => 'Onglet supprime']);
    }

    /**
     * POST /api/admin/menu/reorder
     */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.id' => 'required|integer|exists:menu_items,id',
            'items.*.position' => 'required|integer|min:1',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $item) {
                MenuItem::whereKey($item['id'])->update(['position' => $item['position']]);
            }
        });

        $this->normalizePositions();

        return response()->json([
            'message' => 'Ordre du menu mis a jour',
            'items' => MenuItem::ordered()->get()->map(fn (MenuItem $item) => $item->toFrontend()),
        ]);
    }

    private function validatedData(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        $data = $request->validate([
            'label_fr' => [$required, 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'type' => [$required, 'in:page,url'],
            'page_key' => ['nullable', 'string', 'max:80'],
            'url' => ['nullable', 'string', 'max:500'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = $data['type'] ?? $request->input('type');

        if ($type === 'page' && empty($data['page_key']) && !$partial) {
            throw ValidationException::withMessages([
                'page_key' => 'La page interne est obligatoire.',
            ]);
        }

        if ($type === 'url' && empty($data['url']) && !$partial) {
            throw ValidationException::withMessages([
                'url' => 'L URL externe est obligatoire.',
            ]);
        }

        return $data;
    }

    private function uniqueSlug(string $label): string
    {
        $base = Str::slug($label) ?: 'menu';
        $slug = $base;
        $index = 2;

        while (MenuItem::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $index;
            $index++;
        }

        return $slug;
    }

    private function normalizePositions(): void
    {
        MenuItem::ordered()->get()->each(function (MenuItem $item, int $index) {
            $item->update(['position' => $index + 1]);
        });
    }
}
