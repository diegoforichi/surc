<?php

use App\Actions\Templates\SyncNetworkHistoryCatalog;
use App\Models\Network;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('networks', function (Blueprint $table) {
            $table->string('slogan')->nullable()->after('primary_color');
            $table->text('description')->nullable()->after('slogan');
            $table->string('phone', 50)->nullable()->after('description');
            $table->string('email')->nullable()->after('phone');
            $table->string('whatsapp', 50)->nullable()->after('email');
            $table->string('address')->nullable()->after('whatsapp');
        });

        Network::query()
            ->where('slug', 'civetdur')
            ->where(function ($query): void {
                $query->whereNull('slogan')->orWhere('slogan', '');
            })
            ->update([
                'slogan' => 'Clínicas veterinarias de Durazno, trabajando en red.',
            ]);

        Network::query()
            ->where('industry_template_key', 'veterinary')
            ->each(function (Network $network): void {
                app(SyncNetworkHistoryCatalog::class)->handle($network);
            });
    }

    public function down(): void
    {
        Schema::table('networks', function (Blueprint $table) {
            $table->dropColumn([
                'slogan',
                'description',
                'phone',
                'email',
                'whatsapp',
                'address',
            ]);
        });
    }
};
