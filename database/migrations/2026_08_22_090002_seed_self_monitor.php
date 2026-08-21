<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $url = rtrim(config('app.url'), '/') . '/up';

        DB::table('monitors')->updateOrInsert(
            ['url' => $url],
            [
                'name' => 'My System',
                'check_interval_minutes' => 5,
                'timeout_seconds' => 10,
                'check_ssl' => true,
                'is_active' => true,
                'uptime_status' => 'not_yet_checked',
                'ssl_status' => 'not_yet_checked',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        $url = rtrim(config('app.url'), '/') . '/up';

        DB::table('monitors')->where('url', $url)->delete();
    }
};
