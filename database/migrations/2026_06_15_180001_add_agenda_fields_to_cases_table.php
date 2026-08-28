<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->foreignId('agenda_id')->nullable()->after('current_stage_id')->constrained()->nullOnDelete();
            $table->dateTime('scheduled_at')->nullable()->after('agenda_id');

            $table->index(['agenda_id', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropForeign(['agenda_id']);
            $table->dropColumn(['agenda_id', 'scheduled_at']);
        });
    }
};
