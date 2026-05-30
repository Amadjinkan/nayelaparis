<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('label_fr', 120);
            $table->string('label_en', 120)->nullable();
            $table->enum('type', ['page', 'url'])->default('page');
            $table->string('page_key', 80)->nullable();
            $table->string('url', 500)->nullable();
            $table->unsignedInteger('position')->default(1);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->index(['is_active', 'position']);
            $table->index('type');
        });

        DB::table('menu_items')->insert([
            ['slug' => 'accueil', 'label_fr' => 'Accueil', 'label_en' => 'Home', 'type' => 'page', 'page_key' => 'accueil', 'url' => null, 'position' => 1, 'is_active' => true, 'is_locked' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'boutique', 'label_fr' => 'Boutique', 'label_en' => 'Shop', 'type' => 'page', 'page_key' => 'boutique', 'url' => null, 'position' => 2, 'is_active' => true, 'is_locked' => true, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'collections', 'label_fr' => 'Collections', 'label_en' => 'Collections', 'type' => 'page', 'page_key' => 'collections', 'url' => null, 'position' => 3, 'is_active' => true, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'contact', 'label_fr' => 'Contact', 'label_en' => 'Contact', 'type' => 'page', 'page_key' => 'contact', 'url' => null, 'position' => 4, 'is_active' => true, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()],
            ['slug' => 'a-propos', 'label_fr' => 'A propos', 'label_en' => 'About', 'type' => 'page', 'page_key' => 'accueil', 'url' => null, 'position' => 5, 'is_active' => true, 'is_locked' => false, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
