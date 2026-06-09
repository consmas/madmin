<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_recommendations', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable()->index();
            $table->unsignedBigInteger('store_id')->nullable()->index();
            $table->string('recommendation_type')->index();
            $table->integer('rank')->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->string('outcome')->nullable()->index();
            $table->timestamps();

            $table->index(['actor_type', 'actor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_recommendations');
    }
};
