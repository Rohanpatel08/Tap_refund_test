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
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('charge_id');           // Tap charge ID being refunded
            $table->string('refund_id')->nullable(); // Tap refund ID (from API)
            $table->decimal('amount', 10, 2);
            $table->string('currency');
            $table->text('description')->nullable();
            $table->text('reason')->nullable();
            $table->string('status');              // e.g. pending, succeeded, failed
            $table->json('response')->nullable();  // Store full response for audit
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tap_refunds');
    }
};
