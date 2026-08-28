<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terminology', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('entity_key');
            $table->string('label');
            $table->string('label_plural')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'entity_key']);
        });

        Schema::create('actor_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('label_plural')->nullable();
            $table->string('category')->default('other');
            $table->boolean('is_user_linkable')->default(false);
            $table->boolean('show_in_directory')->default(false);
            $table->string('icon')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['network_id', 'key']);
        });

        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_template_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_terminal')->default(false);
            $table->timestamps();

            $table->unique(['workflow_template_id', 'key']);
        });

        Schema::create('stage_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_stage_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('checkbox');
            $table->boolean('is_mandatory')->default(true);
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['workflow_stage_id', 'key']);
        });

        Schema::create('custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('entity_type');
            $table->foreignId('actor_type_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->string('field_type')->default('text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['network_id', 'entity_type', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_definitions');
        Schema::dropIfExists('stage_requirements');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_templates');
        Schema::dropIfExists('actor_types');
        Schema::dropIfExists('terminology');
    }
};
