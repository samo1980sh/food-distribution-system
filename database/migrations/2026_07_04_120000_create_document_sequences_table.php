<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->id();
            $table->string('document_type');
            $table->date('sequence_date');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['document_type', 'sequence_date'], 'document_sequences_type_date_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
