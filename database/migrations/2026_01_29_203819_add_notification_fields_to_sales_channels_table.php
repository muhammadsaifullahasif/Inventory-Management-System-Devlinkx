<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            // Platform Notifications (Trading API)
            $table->boolean('platform_notifications_enabled')->default(false)->after('delete_status');
            $table->json('platform_notification_events')->nullable()->after('platform_notifications_enabled');

            // Commerce Notification API (REST)
            $table->string('notification_destination_id')->nullable()->after('platform_notification_events');
            $table->string('notification_verification_token', 100)->nullable()->after('notification_destination_id');
            $table->json('notification_subscriptions')->nullable()->after('notification_verification_token');

            // Webhook URL for this sales channel
            $table->string('webhook_url')->nullable()->after('notification_subscriptions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->dropColumn([
                'platform_notifications_enabled',
                'platform_notification_events',
                'notification_destination_id',
                'notification_verification_token',
                'notification_subscriptions',
                'webhook_url',
            ]);
        });
    }
};
