<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kois', function (Blueprint $table) {
            $table->id();

            // SELECT: single value from options
            $table->string('status')->default('active');

            // INTEGER: whole number
            $table->integer('count')->default(0);

            // TEXT: searchable text
            $table->string('name');

            // BOOLEAN: yes/no
            $table->boolean('is_active')->default(true);

            // Nullable field for EMPTY/NOT_EMPTY tests
            $table->string('variety')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kois');
    }
};
