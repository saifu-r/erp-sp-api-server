<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    // add_batch_fields_to_productions_table
    public function up(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            $table->decimal('rate_per_unit', 12, 2)->after('making_house_id');
            $table->decimal('estimated_unit', 12, 3)->after('rate_per_unit');
            $table->decimal('estimated_avg_cost', 12, 4)->after('estimated_unit');
            $table->decimal('actual_unit', 12, 3)->nullable()->after('estimated_avg_cost');
            $table->decimal('final_avg_cost', 12, 4)->nullable()->after('actual_unit');
            $table->decimal('cost_variance', 14, 2)->nullable()->after('final_avg_cost'); // + or - value adjusted at finalize
            $table->tinyInteger('job_status')->default(1); // 1 In Progress, 2 Finalized
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('productions', function (Blueprint $table) {
            //
        });
    }
};
