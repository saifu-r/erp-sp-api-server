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
    Schema::table('journal_entries', function (Blueprint $table) {
        $table->index('date');
    });
    Schema::table('transaction_payments', function (Blueprint $table) {
        $table->index('date');
    });
    Schema::table('expenses', function (Blueprint $table) {
        $table->index('date');
    });
    // journal_entry_lines.account_id and transactions.type/date are already indexed
    // from earlier migrations — confirmed, not duplicated here
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
