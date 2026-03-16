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
    Schema::table('ministeres', function (Blueprint $table) {
        $table->enum('type', [
            'eglise',
            'ministere',
            'organisation',
            'para_ecclesial',
            'mission'
        ])->default('eglise')->after('nom');
    });
}

public function down(): void
{
    Schema::table('ministeres', function (Blueprint $table) {
        $table->dropColumn('type');
    });
}
};
