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
    Schema::table('transactions', function (Blueprint $table) {
        $table->foreignId('production_id')->nullable()->after('order_id')->constrained();
        // production_batch_id is no longer used for this — left in place, harmless, avoids a disruptive drop
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
