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
    Schema::create('logs_actions', function (Blueprint $table) {
        $table->id();
        $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
        $table->foreignId('ministere_id')->nullable()->constrained('ministeres')->nullOnDelete();
        $table->string('action');
        $table->string('module');
        $table->text('details')->nullable();
        $table->string('ip')->nullable();
        $table->timestamp('date_action')->useCurrent();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs_actions');
    }
};
