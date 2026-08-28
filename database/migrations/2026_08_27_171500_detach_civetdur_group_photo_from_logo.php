<?php

use App\Models\Network;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Network::query()
            ->where('slug', 'civetdur')
            ->whereColumn('logo_path', 'cover_path')
            ->update(['logo_path' => null]);
    }

    public function down(): void
    {
        Network::query()
            ->where('slug', 'civetdur')
            ->whereNull('logo_path')
            ->whereNotNull('cover_path')
            ->each(function (Network $network): void {
                $network->forceFill([
                    'logo_path' => $network->cover_path,
                ])->save();
            });
    }
};
