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

            // Relation to Pond
            $table->foreignId('pond_id')->nullable()->constrained()->nullOnDelete();

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

            // DECIMAL: weight in kg (stored as decimal)
            $table->decimal('weight', 8, 2)->default(0.00);

            // DECIMAL stored as INTEGER: price in cents (1999 = $19.99)
            $table->integer('price_cents')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kois');
    }
};
