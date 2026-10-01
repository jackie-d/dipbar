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
        Schema::create('note_collections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('note_collection_user', function (Blueprint $table) {
            $table->foreignId('note_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['note_collection_id', 'user_id']);
        });

        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('note_collection_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->string('visibility', 16)->default('private');
            $table->timestamps();

            $table->index(['lat', 'lng']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notes');
        Schema::dropIfExists('note_collection_user');
        Schema::dropIfExists('note_collections');
    }
};
