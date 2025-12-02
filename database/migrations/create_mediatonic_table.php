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
            $table->string('asset_uuid', 36)->index(); // This is the UUID assigned by Mediatonic
            // Polymorphic relation to the owning model (nullable for standalone media records)
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('filename'); // Original filename stored in Mediatonic
            $table->string('alt')->nullable(); // Alt text for the image
            $table->string('title')->nullable(); // Title for the image
            $table->text('description')->nullable(); // Description for the image
            $table->text('caption')->nullable(); // Caption for the image
            $table->json('config')->nullable(); // meta information from Mediatonic about the asset
            $table->timestamps();

            $table->index(['model_type', 'model_id']);
            $table->unique(['asset_uuid', 'model_id']); // Ensure unique combination of asset_uuid and model id so multiple
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(get_mediatonic_table_name());
    }
};
