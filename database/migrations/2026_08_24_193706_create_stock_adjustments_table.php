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
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->enum('adjustable_type', ['raw_material', 'item']);
            $table->foreignId('raw_material_id')->nullable()->constrained();
            $table->foreignId('item_id')->nullable()->constrained();
            $table->enum('location_type', ['company', 'making_house'])->nullable(); // only for raw materials
            $table->unsignedBigInteger('location_id')->nullable();
            $table->decimal('quantity_change', 12, 3); // positive or negative
            $table->decimal('cost_per_unit', 12, 2)->nullable(); // required for positive adjustments
            $table->string('reason');
            $table->date('date');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
