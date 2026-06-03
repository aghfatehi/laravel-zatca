<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('egs_uuid');
            $table->string('serial_number')->nullable();
            $table->text('certificate');
            $table->text('private_key');
            $table->string('secret');
            $table->string('environment')->default('sandbox');
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('egs_uuid');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_certificates');
    }
};
