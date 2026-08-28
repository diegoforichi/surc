<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agendas', function (Blueprint $table): void {
            $table->boolean('is_shared')->default(false)->after('consent_text');
        });
    }

    public function down(): void
    {
        Schema::table('agendas', function (Blueprint $table): void {
            $table->dropColumn('is_shared');
        });
    }
};
