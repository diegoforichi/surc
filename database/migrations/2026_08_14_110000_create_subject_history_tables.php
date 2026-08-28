<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('history_entry_types', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->json('field_schema')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['network_id', 'key']);
        });

        Schema::create('subject_history_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->foreignId('history_entry_type_id')->constrained()->restrictOnDelete();
            $table->timestamp('occurred_at');
            $table->string('summary')->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('author_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('author_party_id')->nullable()->constrained('parties')->nullOnDelete();
            $table->foreignId('source_case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->foreignId('addendum_of_id')->nullable()->constrained('subject_history_entries')->nullOnDelete();
            $table->foreignId('finalized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->index(['subject_id', 'occurred_at']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('case_history_shares', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subject_history_entry_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shared_at')->useCurrent();
            $table->timestamps();

            $table->unique(['case_id', 'subject_history_entry_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_history_shares');
        Schema::dropIfExists('subject_history_entries');
        Schema::dropIfExists('history_entry_types');
    }
};
