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
            $table->foreignId('quotation_id')->nullable()->after('id')->constrained('transactions');
            $table->tinyInteger('order_status')->nullable(); // Quotation lifecycle: 1 Pending, 2 Converted, 3 Rejected, 4 Expired
            $table->tinyInteger('invoice_status')->nullable(); // Order lifecycle: 1 Not Invoiced, 2 Invoiced
            $table->string('invoice_reference_no')->nullable();
            $table->timestamp('invoiced_at')->nullable();

            $table->decimal('subtotal', 14, 2)->nullable();
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->decimal('discount_amount', 14, 2)->default(0);
            $table->decimal('vat_percent', 5, 2)->default(0);
            $table->decimal('vat_amount', 14, 2)->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            //
        });
    }
};
