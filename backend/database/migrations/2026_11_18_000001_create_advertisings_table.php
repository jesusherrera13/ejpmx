<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('advertisings', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content')->nullable();
            $table->foreignId('content_type_id')->constrained()->restrictOnDelete();
            // Un patrocinador con publicidad no se puede borrar (se desactiva)
            $table->foreignId('sponsor_id')->constrained()->restrictOnDelete();
            $table->string('image')->nullable();
            $table->string('video_url', 500)->nullable();
            $table->string('link_url', 500)->nullable();
            // Vigencia: de starts_at a ends_at, o siempre si is_permanent
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->boolean('is_permanent')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('advertisings');
    }
};
