<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_tool_calls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ai_message_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name')->index();
            $table->string('input_hash')->nullable();
            $table->json('input_summary')->nullable();
            $table->json('output_summary')->nullable();
            $table->string('status')->default('success')->index();
            $table->text('error_message')->nullable();
            $table->integer('latency_ms')->nullable();
            $table->boolean('requires_human_review')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_calls');
    }
};
