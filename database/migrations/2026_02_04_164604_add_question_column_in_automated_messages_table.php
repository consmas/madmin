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
        if (! Schema::hasColumn('automated_messages', 'question_for')) {
            Schema::table('automated_messages', function (Blueprint $table) {
                $table->string('question_for')->default('customer')->after('id');
            });
        }

        if (! Schema::hasColumn('automated_messages', 'question')) {
            Schema::table('automated_messages', function (Blueprint $table) {
                $table->string('question')->after('question_for');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('automated_messages', 'question')) {
            Schema::table('automated_messages', function (Blueprint $table) {
                $table->dropColumn('question');
            });
        }

        if (Schema::hasColumn('automated_messages', 'question_for')) {
            Schema::table('automated_messages', function (Blueprint $table) {
                $table->dropColumn('question_for');
            });
        }
    }
};
