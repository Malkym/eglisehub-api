<?php

namespace Database\Seeders;

//use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
public function run(): void
{
    // Créer le ministère CRC
    $crc = \App\Models\Ministere::create([
        'nom'               => 'Centre Révélation du Christ',
        'slug'              => 'crc',
        'sous_domaine'      => 'crc',
        'description'       => 'Ministère chrétien basé à Bangui',
        'couleur_primaire'  => '#1E3A8A',
        'couleur_secondaire'=> '#FFFFFF',
        'email_contact'     => 'contact@crc.eglisehub.org',
        'pays'              => 'République centrafricaine',
        'statut'            => 'actif',
    ]);

    // Créer le super admin
    \App\Models\User::create([
        'name'          => 'Super Admin',
        'prenom'        => 'EgliseHub',
        'email'         => 'admin@eglisehub.org',
        'password'      => bcrypt('password123'),
        'role'          => 'super_admin',
        'ministere_id'  => null,
        'actif'         => true,
    ]);

    // Créer l'admin CRC
    \App\Models\User::create([
        'name'          => 'Mologbama',
        'prenom'        => 'Abishadai',
        'email'         => 'admin@crc.eglisehub.org',
        'password'      => bcrypt('password123'),
        'role'          => 'admin_ministere',
        'ministere_id'  => $crc->id,
        'actif'         => true,
    ]);
}
}
