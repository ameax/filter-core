<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ponds', function (Blueprint $table) {
            $table->id();

            // SELECT: water type
            $table->string('water_type')->default('fresh');

            // INTEGER: capacity in liters
            $table->integer('capacity')->default(1000);

            // TEXT: name
            $table->string('name');

            // BOOLEAN: is heated
            $table->boolean('is_heated')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ponds');
    }
};
