<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->string('prenom')->nullable()->after('name');
        $table->foreignId('ministere_id')
              ->nullable()
              ->constrained('ministeres')
              ->nullOnDelete()
              ->after('prenom');
        $table->enum('role', ['super_admin', 'admin_ministere'])
              ->default('admin_ministere')
              ->after('ministere_id');
        $table->timestamp('dernier_login')->nullable();
        $table->boolean('actif')->default(true);
    });
}

public function down(): void
{
    Schema::table('users', function (Blueprint $table) {
        $table->dropForeign(['ministere_id']);
        $table->dropColumn(['prenom', 'ministere_id', 'role', 'dernier_login', 'actif']);
    });
}
};
