<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 120);
            $table->string('slug', 140)->unique();
            $table->string('label_en', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('age_range', 120)->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['actif', 'position']);
        });

        Schema::create('site_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->text('value')->nullable();
            $table->string('group_name', 80)->default('general');
            $table->timestamps();
        });

        Schema::create('site_banners', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120)->unique();
            $table->string('eyebrow_fr', 200)->nullable();
            $table->string('eyebrow_en', 200)->nullable();
            $table->string('title_fr', 240);
            $table->string('title_en', 240)->nullable();
            $table->text('subtitle_fr')->nullable();
            $table->text('subtitle_en')->nullable();
            $table->string('primary_label_fr', 120)->nullable();
            $table->string('primary_label_en', 120)->nullable();
            $table->string('primary_page', 80)->nullable();
            $table->string('secondary_label_fr', 120)->nullable();
            $table->string('secondary_label_en', 120)->nullable();
            $table->string('secondary_page', 80)->nullable();
            $table->string('image', 500)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->index(['actif', 'position']);
        });

        Schema::create('site_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_fr', 200);
            $table->string('title_en', 200)->nullable();
            $table->string('subtitle_fr', 240)->nullable();
            $table->string('subtitle_en', 240)->nullable();
            $table->longText('content_fr')->nullable();
            $table->longText('content_en')->nullable();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('produits', function (Blueprint $table) {
            if (!Schema::hasColumn('produits', 'couleurs')) {
                $table->string('couleurs', 200)->nullable()->after('tailles');
            }
        });

        $now = now();

        $categories = [
            ['nom' => 'Bébé', 'label_en' => 'Baby', 'description' => 'Pièces délicates pour les premiers mois.', 'age_range' => '0 - 24 mois', 'position' => 1],
            ['nom' => 'Fille', 'label_en' => 'Girl', 'description' => 'Robes et tenues raffinées.', 'age_range' => '2 - 12 ans', 'position' => 2],
            ['nom' => 'Garçon', 'label_en' => 'Boy', 'description' => 'Classiques élégants pour garçon.', 'age_range' => '2 - 12 ans', 'position' => 3],
            ['nom' => 'Duo', 'label_en' => 'Duo', 'description' => 'Sets coordonnés.', 'age_range' => 'Sets coordonnés', 'position' => 4],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                ...$category,
                'slug' => Str::slug($category['nom']),
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $settings = [
            ['key' => 'brand_name', 'value' => 'NayeLa Paris', 'group_name' => 'general'],
            ['key' => 'brand_slogan', 'value' => "Vêtu d'amour, né pour briller", 'group_name' => 'general'],
            ['key' => 'topbar_message', 'value' => 'Livraison offerte dès 120 CAD · Collections Printemps-Été 2026 disponibles', 'group_name' => 'general'],
            ['key' => 'contact_phone', 'value' => '+1 437 000 0000', 'group_name' => 'contact'],
            ['key' => 'contact_email', 'value' => 'contact@nayelaparis.com', 'group_name' => 'contact'],
            ['key' => 'whatsapp_number', 'value' => '', 'group_name' => 'contact'],
            ['key' => 'instagram_url', 'value' => 'https://www.instagram.com/', 'group_name' => 'social'],
            ['key' => 'tiktok_url', 'value' => 'https://www.tiktok.com/', 'group_name' => 'social'],
            ['key' => 'facebook_url', 'value' => 'https://www.facebook.com/nayelaparis', 'group_name' => 'social'],
        ];

        foreach ($settings as $setting) {
            DB::table('site_settings')->insert([...$setting, 'created_at' => $now, 'updated_at' => $now]);
        }

        DB::table('site_banners')->insert([
            'key' => 'home',
            'eyebrow_fr' => 'Collection Printemps-Été 2026',
            'eyebrow_en' => 'Spring-Summer 2026 Collection',
            'title_fr' => "L'élégance à la française pour vos enfants",
            'title_en' => 'French elegance for your children',
            'subtitle_fr' => 'Des créations raffinées pour les enfants de 0 à 12 ans. Qualité premium, inspirée des maisons parisiennes.',
            'subtitle_en' => 'Refined creations for children from 0 to 12. Premium quality inspired by Parisian houses.',
            'primary_label_fr' => 'Découvrir la boutique',
            'primary_label_en' => 'Explore the shop',
            'primary_page' => 'boutique',
            'secondary_label_fr' => 'Nos collections',
            'secondary_label_en' => 'Our collections',
            'secondary_page' => 'collections',
            'position' => 1,
            'actif' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('site_pages')->insert([
            [
                'slug' => 'contact',
                'title_fr' => 'Contact',
                'title_en' => 'Contact',
                'subtitle_fr' => 'Service client',
                'subtitle_en' => 'Customer care',
                'content_fr' => 'Notre équipe vous répond avec attention.',
                'content_en' => 'Our team will answer you with care.',
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'slug' => 'about',
                'title_fr' => 'À propos',
                'title_en' => 'About',
                'subtitle_fr' => 'Maison NayeLa Paris',
                'subtitle_en' => 'NayeLa Paris house',
                'content_fr' => "Une marque pensée pour célébrer l'élégance enfantine avec douceur, exigence et amour.",
                'content_en' => 'A brand created to celebrate children’s elegance with softness, care and love.',
                'actif' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            if (Schema::hasColumn('produits', 'couleurs')) {
                $table->dropColumn('couleurs');
            }
        });

        Schema::dropIfExists('site_pages');
        Schema::dropIfExists('site_banners');
        Schema::dropIfExists('site_settings');
        Schema::dropIfExists('categories');
    }
};
