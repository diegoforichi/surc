<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subject_history_entries', function (Blueprint $table): void {
            $table->timestamp('next_due_at')->nullable()->after('finalized_at');
            $table->index(['organization_id', 'next_due_at']);
        });

        DB::table('subject_history_entries')
            ->whereNotNull('payload')
            ->orderBy('id')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $payload = json_decode((string) $row->payload, true);
                    $raw = is_array($payload) ? ($payload['next_due'] ?? null) : null;

                    if (! is_string($raw) || trim($raw) === '') {
                        continue;
                    }

                    try {
                        $due = Carbon::parse($raw);
                    } catch (Throwable) {
                        continue;
                    }

                    DB::table('subject_history_entries')
                        ->where('id', $row->id)
                        ->update(['next_due_at' => $due]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('subject_history_entries', function (Blueprint $table): void {
            $table->dropIndex(['organization_id', 'next_due_at']);
            $table->dropColumn('next_due_at');
        });
    }
};
