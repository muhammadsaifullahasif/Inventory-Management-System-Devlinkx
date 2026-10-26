<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * - Encrypts eBay / carrier secrets at rest (SalesChannel and Shipping now
 *   use Laravel's "encrypted" casts, keyed by APP_KEY).
 * - Strips those secrets out of audit rows that were written before they
 *   were excluded in config/audit.php.
 * - Login codes are now stored as an HMAC (see LoginOtp::hashCode()), so
 *   the column is widened and any outstanding plain-text codes are voided.
 */
return new class extends Migration
{
    private const SECRET_COLUMNS = [
        'sales_channels' => ['client_secret', 'authorization_code', 'access_token', 'refresh_token', 'notification_verification_token'],
        'shippings' => ['credentials', 'authorization_code', 'access_token', 'refresh_token'],
    ];

    private const AUDIT_SECRET_KEYS = [
        'client_secret', 'authorization_code', 'access_token', 'refresh_token',
        'notification_verification_token', 'credentials',
    ];

    public function up(): void
    {
        // Ciphertext is far longer than the plain value, and is not JSON.
        Schema::table('sales_channels', function (Blueprint $table) {
            $table->text('client_secret')->change();
            $table->text('notification_verification_token')->nullable()->change();
        });
        Schema::table('shippings', function (Blueprint $table) {
            $table->longText('credentials')->nullable()->change();
        });
        Schema::table('login_otps', function (Blueprint $table) {
            $table->string('code', 64)->change();
        });

        foreach (self::SECRET_COLUMNS as $table => $columns) {
            $this->transformColumns($table, $columns, function (string $value) {
                return $this->isEncrypted($value) ? $value : Crypt::encryptString($value);
            });
        }

        foreach (['audit_logs', 'audit_log_archives'] as $table) {
            if (Schema::hasTable($table)) {
                $this->scrubAuditTable($table);
            }
        }

        DB::table('login_otps')->whereNull('used_at')->update(['used_at' => now()]);
    }

    public function down(): void
    {
        foreach (self::SECRET_COLUMNS as $table => $columns) {
            $this->transformColumns($table, $columns, function (string $value) {
                return $this->isEncrypted($value) ? Crypt::decryptString($value) : $value;
            });
        }

        // Hashed codes can't be turned back into 6-digit ones; void them.
        DB::table('login_otps')->whereNull('used_at')->update(['used_at' => now()]);
        DB::table('login_otps')->update(['code' => '000000']);

        Schema::table('login_otps', function (Blueprint $table) {
            $table->string('code', 6)->change();
        });
        // Columns stay as TEXT: shrinking them back could truncate real values.
    }

    private function transformColumns(string $table, array $columns, callable $transform): void
    {
        DB::table($table)->select(array_merge(['id'], $columns))->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $columns, $transform) {
                foreach ($rows as $row) {
                    $updates = [];
                    foreach ($columns as $column) {
                        if ($row->{$column} !== null && $row->{$column} !== '') {
                            $updates[$column] = $transform($row->{$column});
                        }
                    }
                    if ($updates) {
                        DB::table($table)->where('id', $row->id)->update($updates);
                    }
                }
            });
    }

    private function scrubAuditTable(string $table): void
    {
        $query = DB::table($table)->select('id', 'old_values', 'new_values', 'context');
        $query->where(function ($q) {
            foreach (['old_values', 'new_values', 'context'] as $column) {
                foreach (self::AUDIT_SECRET_KEYS as $key) {
                    $q->orWhere($column, 'like', '%"' . $key . '"%');
                }
            }
        });

        $query->orderBy('id')->chunkById(200, function ($rows) use ($table) {
            foreach ($rows as $row) {
                $updates = [];
                foreach (['old_values', 'new_values', 'context'] as $column) {
                    $decoded = $row->{$column} === null ? null : json_decode($row->{$column}, true);
                    if (!is_array($decoded)) {
                        continue;
                    }
                    $scrubbed = $this->withoutSecretKeys($decoded);
                    if ($scrubbed !== $decoded) {
                        $updates[$column] = $scrubbed ? json_encode($scrubbed) : null;
                    }
                }
                if ($updates) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                }
            }
        });
    }

    private function withoutSecretKeys(array $values): array
    {
        foreach ($values as $key => $value) {
            if (in_array($key, self::AUDIT_SECRET_KEYS, true)) {
                unset($values[$key]);
            } elseif (is_array($value)) {
                $values[$key] = $this->withoutSecretKeys($value);
            }
        }

        return $values;
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
