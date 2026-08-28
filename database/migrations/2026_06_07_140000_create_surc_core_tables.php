<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('networks', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('industry_template_key')->default('generic');
            $table->string('logo_path')->nullable();
            $table->string('primary_color', 20)->default('#f59e0b');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('network_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('show_in_directory')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['network_id', 'slug']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('network_id')->references('id')->on('networks')->nullOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['network_id']);
            $table->dropForeign(['organization_id']);
        });

        Schema::dropIfExists('organizations');
        Schema::dropIfExists('networks');
    }
};
