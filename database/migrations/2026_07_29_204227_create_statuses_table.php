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
        Schema::create('statuses', function (Blueprint $table) {
            $table->id();
            $table->string('group');      // e.g. 'general', 'payment', 'order'
            $table->unsignedTinyInteger('code'); // the actual stored value, e.g. 0, 1, 2
            $table->string('label');      // e.g. 'Active', 'Pending'
            $table->timestamps();
            $table->unique(['group', 'code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statuses');
    }
};
