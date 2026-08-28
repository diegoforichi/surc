<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agendas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('specialist_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->string('title')->nullable();
            $table->date('scheduled_date');
            $table->time('start_time')->nullable();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['network_id', 'scheduled_date']);
            $table->index(['organization_id', 'scheduled_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agendas');
    }
};
