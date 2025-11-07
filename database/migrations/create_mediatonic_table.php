<?php

use Digitonic\Mediatonic\Filament\Models\Media;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $mediaModel = config('mediatonic.media_model', Media::class);
        $tableName = (new $mediaModel)->getTable();
        Schema::create($tableName, function (Blueprint $table) {
            $table->id();
            // Polymorphic relation to the owning model
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('uuid', 36)->index();
            $table->string('filename');
            $table->json('presets')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        $mediaModel = config('mediatonic.media_model', Media::class);
        $tableName = (new $mediaModel)->getTable();
        Schema::dropIfExists($tableName);
    }
};
