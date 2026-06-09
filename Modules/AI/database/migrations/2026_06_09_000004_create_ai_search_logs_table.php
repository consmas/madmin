<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_search_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->text('query');
            $table->json('filters')->nullable();
            $table->integer('result_count')->default(0);
            $table->json('result_item_ids')->nullable();
            $table->unsignedBigInteger('clicked_item_id')->nullable()->index();
            $table->string('source')->default('ai_product_search')->index();
            $table->integer('latency_ms')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_logs');
    }
};
