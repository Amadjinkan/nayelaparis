<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ===== Administrateur par défaut =====
        User::firstOrCreate(
            ['email' => 'admin@nayelaparis.com'],
            [
                'prenom' => 'Admin',
                'nom' => 'NayeLa',
                'mot_de_passe' => Hash::make('admin1234'),
                'role' => 'admin',
            ]
        );

        // ===== Compte client de test =====
        User::firstOrCreate(
            ['email' => 'client@test.com'],
            [
                'prenom' => 'Marie',
                'nom' => 'Tremblay',
                'mot_de_passe' => Hash::make('client1234'),
                'role' => 'client',
                'telephone' => '+1 514 555 1234',
            ]
        );

        // ===== Catalogue NayeLa Paris =====
        $produits = [
            ['nom' => 'Robe Élégante Floral', 'categorie' => 'Robes', 'prix' => 89.00, 'stock' => 12, 'tailles' => '2 ans, 4 ans, 6 ans, 8 ans', 'description' => 'Robe à motifs floraux délicats, coupe princesse, parfaite pour les occasions spéciales.', 'emoji' => '👗', 'featured' => true],
            ['nom' => 'Costume Classique Bleu', 'categorie' => 'Costumes', 'prix' => 145.00, 'stock' => 8, 'tailles' => '4 ans, 6 ans, 8 ans, 10 ans', 'description' => 'Costume trois pièces en laine fine, idéal pour cérémonies et mariages.', 'emoji' => '🤵', 'featured' => true],
            ['nom' => 'Tutu Princesse Rose', 'categorie' => 'Robes', 'prix' => 65.00, 'stock' => 20, 'tailles' => '2 ans, 3 ans, 4 ans, 5 ans', 'description' => 'Tutu en tulle multi-couches, rose poudré, magique pour les petites danseuses.', 'emoji' => '🩰', 'featured' => true],
            ['nom' => 'Salopette Lin Crème', 'categorie' => 'Tenues', 'prix' => 72.00, 'stock' => 15, 'tailles' => '12 mois, 18 mois, 24 mois, 3 ans', 'description' => 'Salopette en lin naturel, confortable et élégante pour tous les jours.', 'emoji' => '👶', 'featured' => false],
            ['nom' => 'Chemise Blanche Brodée', 'categorie' => 'Hauts', 'prix' => 49.00, 'stock' => 25, 'tailles' => '4 ans, 6 ans, 8 ans, 10 ans, 12 ans', 'description' => 'Chemise en coton biologique avec broderies fines au col.', 'emoji' => '👔', 'featured' => false],
            ['nom' => 'Manteau Caban Marine', 'categorie' => 'Manteaux', 'prix' => 180.00, 'stock' => 6, 'tailles' => '4 ans, 6 ans, 8 ans, 10 ans', 'description' => 'Caban marine en laine vierge, doublé satin, intemporel.', 'emoji' => '🧥', 'featured' => true],
            ['nom' => 'Chapeau Paille Été', 'categorie' => 'Accessoires', 'prix' => 28.00, 'stock' => 30, 'tailles' => 'S, M, L', 'description' => 'Chapeau en paille naturelle avec ruban en satin, parfait pour l\'été.', 'emoji' => '👒', 'featured' => false],
            ['nom' => 'Sandales Cuir Or', 'categorie' => 'Chaussures', 'prix' => 65.00, 'stock' => 0, 'tailles' => '24, 26, 28, 30, 32', 'description' => 'Sandales en cuir véritable doré, finition artisanale.', 'emoji' => '👡', 'featured' => false],
            ['nom' => 'Robe Communion Blanche', 'categorie' => 'Robes', 'prix' => 220.00, 'stock' => 4, 'tailles' => '6 ans, 7 ans, 8 ans, 9 ans, 10 ans', 'description' => 'Robe de communion en organza et dentelle de Calais, cousue main.', 'emoji' => '👰', 'featured' => true],
            ['nom' => 'Veste Velours Bordeaux', 'categorie' => 'Costumes', 'prix' => 110.00, 'stock' => 3, 'tailles' => '4 ans, 6 ans, 8 ans', 'description' => 'Veste en velours côtelé, doublure satinée bordeaux profond.', 'emoji' => '🧥', 'featured' => false],
            ['nom' => 'Pyjama Soie Bébé', 'categorie' => 'Pyjamas', 'prix' => 95.00, 'stock' => 14, 'tailles' => '0-3 mois, 3-6 mois, 6-12 mois', 'description' => 'Pyjama en soie pour bébé, hypoallergénique et ultra-doux.', 'emoji' => '🌙', 'featured' => false],
            ['nom' => 'Nœud Papillon Tartan', 'categorie' => 'Accessoires', 'prix' => 22.00, 'stock' => 40, 'tailles' => 'Unique', 'description' => 'Nœud papillon en laine tartan classique, élastique réglable.', 'emoji' => '🎀', 'featured' => false],
        ];

        foreach ($produits as $p) {
            Produit::firstOrCreate(['nom' => $p['nom']], $p);
        }

        $this->command->info('✓ ' . User::count() . ' utilisateurs en base');
        $this->command->info('✓ ' . Produit::count() . ' produits en base');
        // ===== Menu administrable =====
        $menuItems = [
            ['slug' => 'accueil', 'label_fr' => 'Accueil', 'label_en' => 'Home', 'type' => 'page', 'page_key' => 'accueil', 'url' => null, 'position' => 1, 'is_active' => true, 'is_locked' => true],
            ['slug' => 'boutique', 'label_fr' => 'Boutique', 'label_en' => 'Shop', 'type' => 'page', 'page_key' => 'boutique', 'url' => null, 'position' => 2, 'is_active' => true, 'is_locked' => true],
            ['slug' => 'collections', 'label_fr' => 'Collections', 'label_en' => 'Collections', 'type' => 'page', 'page_key' => 'collections', 'url' => null, 'position' => 3, 'is_active' => true, 'is_locked' => false],
            ['slug' => 'contact', 'label_fr' => 'Contact', 'label_en' => 'Contact', 'type' => 'page', 'page_key' => 'contact', 'url' => null, 'position' => 4, 'is_active' => true, 'is_locked' => false],
            ['slug' => 'a-propos', 'label_fr' => 'A propos', 'label_en' => 'About', 'type' => 'page', 'page_key' => 'accueil', 'url' => null, 'position' => 5, 'is_active' => true, 'is_locked' => false],
        ];

        foreach ($menuItems as $item) {
            MenuItem::firstOrCreate(['slug' => $item['slug']], $item);
        }

        $this->command->info('Menu : ' . MenuItem::count() . ' onglets en base');
        $this->command->info('');
        $this->command->info('=== Comptes de test ===');
        $this->command->info('Admin  : admin@nayelaparis.com / admin1234');
        $this->command->info('Client : client@test.com / client1234');
    }
}
