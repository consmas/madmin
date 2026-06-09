<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_intent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('intent')->index();
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('location_text')->nullable();
            $table->decimal('budget_min', 20, 4)->nullable();
            $table->decimal('budget_max', 20, 4)->nullable();
            $table->string('urgency')->nullable()->index();
            $table->json('extracted_entities')->nullable();
            $table->decimal('confidence', 5, 4)->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_intent_logs');
    }
};
