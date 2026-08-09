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
            $table->foreignId('user_id')->nullable()->after('id')->constrained(); // who made the sale — nullable, unused in forms for now
            $table->foreign('customer_id')->references('id')->on('customers');
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
            $table->dropForeign(['customer_id']);
        });

        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropForeign(['item_id']);
        });
    }
};
