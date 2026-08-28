<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->text('instructions')->nullable()->after('is_active');
            $table->text('consent_text')->nullable()->after('instructions');
        });

        Schema::table('agendas', function (Blueprint $table): void {
            $table->text('instructions')->nullable()->after('notes');
            $table->text('consent_text')->nullable()->after('instructions');
        });

        Schema::table('cases', function (Blueprint $table): void {
            $table->unsignedInteger('agenda_order')->nullable()->after('agenda_id');
            $table->unique(['agenda_id', 'agenda_order']);
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('whatsapp')->nullable()->after('phone');
            $table->string('website')->nullable()->after('email');
            $table->boolean('history_enabled')->default(false)->after('show_in_directory');
        });

        Schema::table('parties', function (Blueprint $table): void {
            $table->string('whatsapp')->nullable()->after('phone');
        });

        Schema::table('public_content', function (Blueprint $table): void {
            $table->string('excerpt')->nullable()->after('body');
            $table->string('seo_description')->nullable()->after('excerpt');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_templates', function (Blueprint $table): void {
            $table->dropColumn(['instructions', 'consent_text']);
        });

        Schema::table('agendas', function (Blueprint $table): void {
            $table->dropColumn(['instructions', 'consent_text']);
        });

        Schema::table('cases', function (Blueprint $table): void {
            $table->dropUnique(['agenda_id', 'agenda_order']);
            $table->dropColumn('agenda_order');
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn(['whatsapp', 'website', 'history_enabled']);
        });

        Schema::table('parties', function (Blueprint $table): void {
            $table->dropColumn('whatsapp');
        });

        Schema::table('public_content', function (Blueprint $table): void {
            $table->dropColumn(['excerpt', 'seo_description']);
        });
    }
};
