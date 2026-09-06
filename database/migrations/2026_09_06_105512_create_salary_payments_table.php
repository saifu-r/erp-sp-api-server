<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    // create_salary_payments_table
    public function up(): void
    {
        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained();
            $table->string('period_month'); // e.g. "2026-08"
            $table->decimal('base_salary', 12, 2);
            $table->integer('absent_days')->default(0);
            $table->decimal('absence_deduction', 12, 2)->default(0);
            $table->decimal('bonus', 12, 2)->default(0);
            $table->decimal('gross_payable', 12, 2);
            $table->decimal('advance_recovered', 12, 2)->default(0);
            $table->decimal('net_paid', 12, 2);
            $table->string('paid_from'); // cash or bank
            $table->date('date');
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamps();

            $table->unique(['employee_id', 'period_month']); // prevents double-processing the same month
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('salary_payments');
    }
};
