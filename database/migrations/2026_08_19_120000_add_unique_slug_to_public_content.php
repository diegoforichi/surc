<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('public_content', function (Blueprint $table): void {
            $table->unique(['network_id', 'type', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('public_content', function (Blueprint $table): void {
            $table->dropUnique(['network_id', 'type', 'slug']);
        });
    }
};
