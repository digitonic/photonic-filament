<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(get_mediatonic_table_name(), function (Blueprint $table) {
            $table->id();
            $table->string('asset_uuid', 36)->unique()->index(); // This is the UUID assigned by Mediatonic
            // Polymorphic relation to the owning model
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->string('filename'); // Original filename stored in Mediatonic
            $table->json('config')->nullable(); // meta information from Mediatonic about the asset
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(get_mediatonic_table_name());
    }
};
