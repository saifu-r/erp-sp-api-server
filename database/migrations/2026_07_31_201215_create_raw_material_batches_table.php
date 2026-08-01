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
    Schema::create('raw_material_batches', function (Blueprint $table) {
        $table->id();
        $table->foreignId('raw_material_id')->constrained()->onDelete('cascade');
        $table->enum('location_type', ['company', 'making_house']);
        $table->unsignedBigInteger('location_id')->nullable();
        $table->foreignId('source_batch_id')->nullable()->constrained('raw_material_batches');
        $table->decimal('quantity_remaining', 12, 3);
        $table->decimal('cost_per_unit', 12, 2);
        $table->timestamps();

        $table->index(['raw_material_id', 'location_type', 'location_id'], 'rmb_material_location_idx');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('raw_material_batches');
    }
};
