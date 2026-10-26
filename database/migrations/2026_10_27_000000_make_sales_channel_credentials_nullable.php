<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * eBay channels now authorise against the single developer keyset in
 * config/services.php, so new rows store no app credentials of their own.
 * Existing rows keep theirs until they are reconnected (their refresh
 * tokens only work with the keyset that issued them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->string('client_id')->nullable()->change();
            $table->text('client_secret')->nullable()->change();
            $table->text('user_scopes')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Columns stay nullable: channels connected through the shared keyset
        // have no stored credentials to put back.
    }
};
