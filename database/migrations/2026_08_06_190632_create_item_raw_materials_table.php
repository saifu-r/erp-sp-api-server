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
        Schema::create('item_raw_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->onDelete('cascade');
            $table->foreignId('raw_material_id')->constrained();
            $table->decimal('quantity_per_unit', 12, 4); // e.g. 0.5 (kg of Powder per 1 pcs of Box)
            $table->timestamps();

            $table->unique(['item_id', 'raw_material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_raw_materials');
    }
};
