<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zatca_invoice_logs', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_serial');
            $table->string('invoice_uuid')->nullable();
            $table->string('invoice_hash')->nullable();
            $table->string('status')->default('pending');
            $table->string('phase')->default('phase_2');
            $table->string('environment')->default('sandbox');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cleared_at')->nullable();
            $table->timestamps();

            $table->index('invoice_serial');
            $table->index('status');
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zatca_invoice_logs');
    }
};
