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
        Schema::create('productions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained();
            $table->foreignId('making_house_id')->constrained();
            $table->decimal('quantity_produced', 12, 3);
            $table->decimal('wastage_quantity', 12, 3)->default(0);
            $table->foreignId('wastage_raw_material_id')->nullable()->constrained('raw_materials');
            $table->decimal('total_raw_material_cost', 14, 2)->default(0);
            $table->date('date');
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('productions');
    }
};
