<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(config('igs-field.media_table'), function (Blueprint $table) {
            $table->id();
            // Polymorphic relation to the owning model
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('filename');
            // Presets returned from IGS API (if any)
            $table->json('presets')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(config('igs-field.media_table'));
    }
};
