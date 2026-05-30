<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\SiteBanner;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SiteContentController extends Controller
{
    public function publicContent(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsMap(),
            'categories' => Category::visible()->ordered()->get(),
            'banners' => SiteBanner::visible()->ordered()->get(),
            'pages' => SitePage::where('actif', true)->get(),
        ]);
    }

    public function publicCategories(): JsonResponse
    {
        return response()->json(Category::visible()->ordered()->get());
    }

    public function publicSettings(): JsonResponse
    {
        return response()->json($this->settingsMap());
    }

    public function publicBanners(): JsonResponse
    {
        return response()->json(SiteBanner::visible()->ordered()->get());
    }

    public function publicPage(string $slug): JsonResponse
    {
        return response()->json(SitePage::where('slug', $slug)->where('actif', true)->firstOrFail());
    }

    public function adminContent(): JsonResponse
    {
        return response()->json([
            'settings' => $this->settingsMap(),
            'categories' => Category::ordered()->get(),
            'banners' => SiteBanner::ordered()->get(),
            'pages' => SitePage::orderBy('slug')->get(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'settings' => 'required|array',
            'settings.*' => 'nullable|string|max:1000',
        ]);

        foreach ($data['settings'] as $key => $value) {
            SiteSetting::updateOrCreate(
                ['key' => $key],
                ['value' => $value, 'group_name' => $this->settingGroup($key)]
            );
        }

        return response()->json([
            'message' => 'Paramètres mis à jour',
            'settings' => $this->settingsMap(),
        ]);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $this->validateCategory($request);
        $category = Category::create([
            ...$data,
            'slug' => $this->uniqueCategorySlug($data['nom']),
            'position' => Category::max('position') + 1,
            'actif' => $data['actif'] ?? true,
        ]);

        return response()->json(['message' => 'Catégorie créée', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, int $id): JsonResponse
    {
        $category = Category::findOrFail($id);
        $data = $this->validateCategory($request, partial: true);
        $category->update($data);

        return response()->json(['message' => 'Catégorie mise à jour', 'category' => $category->fresh()]);
    }

    public function destroyCategory(int $id): JsonResponse
    {
        Category::findOrFail($id)->update(['actif' => false]);
        return response()->json(['message' => 'Catégorie masquée']);
    }

    public function storeBanner(Request $request): JsonResponse
    {
        $data = $this->validateBanner($request);
        $banner = SiteBanner::create([
            ...$data,
            'key' => $data['key'] ?? $this->uniqueBannerKey($data['title_fr']),
            'position' => SiteBanner::max('position') + 1,
            'actif' => $data['actif'] ?? true,
        ]);

        return response()->json(['message' => 'Bannière créée', 'banner' => $banner], 201);
    }

    public function updateBanner(Request $request, int $id): JsonResponse
    {
        $banner = SiteBanner::findOrFail($id);
        $data = $this->validateBanner($request, partial: true);
        $banner->update($data);

        return response()->json(['message' => 'Bannière mise à jour', 'banner' => $banner->fresh()]);
    }

    public function destroyBanner(int $id): JsonResponse
    {
        SiteBanner::findOrFail($id)->update(['actif' => false]);
        return response()->json(['message' => 'Bannière masquée']);
    }

    public function storePage(Request $request): JsonResponse
    {
        $data = $this->validatePage($request);
        $page = SitePage::create([
            ...$data,
            'slug' => Str::slug($data['slug']),
            'actif' => $data['actif'] ?? true,
        ]);

        return response()->json(['message' => 'Page créée', 'page' => $page], 201);
    }

    public function updatePage(Request $request, int $id): JsonResponse
    {
        $page = SitePage::findOrFail($id);
        $data = $this->validatePage($request, partial: true);
        if (isset($data['slug'])) {
            $data['slug'] = Str::slug($data['slug']);
        }
        $page->update($data);

        return response()->json(['message' => 'Page mise à jour', 'page' => $page->fresh()]);
    }

    public function destroyPage(int $id): JsonResponse
    {
        SitePage::findOrFail($id)->update(['actif' => false]);
        return response()->json(['message' => 'Page masquée']);
    }

    private function settingsMap(): array
    {
        return SiteSetting::query()->pluck('value', 'key')->toArray();
    }

    private function settingGroup(string $key): string
    {
        return str_starts_with($key, 'contact_') || str_contains($key, 'whatsapp') ? 'contact'
            : (str_ends_with($key, '_url') ? 'social' : 'general');
    }

    private function validateCategory(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'nom' => [$required, 'string', 'max:120'],
            'label_en' => ['nullable', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'age_range' => ['nullable', 'string', 'max:120'],
            'image' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:1'],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    private function validateBanner(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'key' => ['nullable', 'string', 'max:120'],
            'eyebrow_fr' => ['nullable', 'string', 'max:200'],
            'eyebrow_en' => ['nullable', 'string', 'max:200'],
            'title_fr' => [$required, 'string', 'max:240'],
            'title_en' => ['nullable', 'string', 'max:240'],
            'subtitle_fr' => ['nullable', 'string'],
            'subtitle_en' => ['nullable', 'string'],
            'primary_label_fr' => ['nullable', 'string', 'max:120'],
            'primary_label_en' => ['nullable', 'string', 'max:120'],
            'primary_page' => ['nullable', 'string', 'max:80'],
            'secondary_label_fr' => ['nullable', 'string', 'max:120'],
            'secondary_label_en' => ['nullable', 'string', 'max:120'],
            'secondary_page' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:1'],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    private function validatePage(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'slug' => [$required, 'string', 'max:120'],
            'title_fr' => [$required, 'string', 'max:200'],
            'title_en' => ['nullable', 'string', 'max:200'],
            'subtitle_fr' => ['nullable', 'string', 'max:240'],
            'subtitle_en' => ['nullable', 'string', 'max:240'],
            'content_fr' => ['nullable', 'string'],
            'content_en' => ['nullable', 'string'],
            'actif' => ['nullable', 'boolean'],
        ]);
    }

    private function uniqueCategorySlug(string $name): string
    {
        $base = Str::slug($name) ?: 'categorie';
        $slug = $base;
        $index = 2;

        while (Category::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $index++;
        }

        return $slug;
    }

    private function uniqueBannerKey(string $title): string
    {
        $base = Str::slug($title) ?: 'banner';
        $key = $base;
        $index = 2;

        while (SiteBanner::where('key', $key)->exists()) {
            $key = $base . '-' . $index++;
        }

        return $key;
    }
}
