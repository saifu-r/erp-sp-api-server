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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'purchase' | 'order' | 'production' | 'adjustment' | 'delivery'
            $table->string('reference_no')->unique();
            $table->foreignId('supplier_id')->nullable()->constrained();
            $table->foreignId('customer_id')->nullable(); // FK added once Customers exist
            $table->foreignId('making_house_id')->nullable()->constrained();
            $table->date('date');
            $table->decimal('total_amount', 14, 2)->nullable();
            $table->decimal('paid_amount', 14, 2)->default(0);
            $table->tinyInteger('payment_status')->nullable(); // 1 Pending, 2 Partial, 3 Paid
            $table->tinyInteger('status')->default(1);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['type', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
