# Backup & Restore Runbook

Backups are handled by `spatie/laravel-backup`. Config: [config/backup.php](config/backup.php).

## What's backed up

- **Database**: full dump of the `mysql` connection — every table (accounting,
  catalog, sales, purchasing, RBAC, everything). No table exclusions.
- **Files**: `storage/app` (product images, dompdf PDF reports, uploads) and
  `storage/logs`. `storage/framework/cache` is excluded from the zip.
- **Destination**: local disk `backups` → `storage/app/backups/{app-name}/`.
  (S3 secondary destination is stubbed but commented out in
  `config/filesystems.php` — not active until credentials are added.)

## Schedule (routes/console.php)

| Command | When | Purpose |
|---|---|---|
| `backup:run` | daily 02:00 | full DB + files backup |
| `backup:clean` | Monday 03:00 | retention: 7 daily / 4 weekly / 3 monthly |
| `backup:monitor` | daily 02:30 | flags missing/stale/oversized backups |

Failures and unhealthy-backup findings fire `BackupHasFailed`,
`CleanupHasFailed`, `UnhealthyBackupWasFound` — handled in
`AppServiceProvider::boot()` as `Log::critical` + Sentry (if configured).
These do **not** fail silently.

## Manual backup

```
php artisan backup:run
```

Zip lands in `storage/app/backups/{app-name}/YYYY-MM-DD-HH-mm-ss.zip`.
Check `storage/logs/laravel.log` (or `backup-run.log`) if it doesn't appear.

## Restore procedure

1. **Pull the zip off the server** to a scratch machine — don't restore
   on top of live prod without a fresh DB to test against first.

2. **Unzip it**:
   ```
   unzip 2026-08-21-02-00-00.zip -d restore_test
   ```
   Inside: `db-dumps/mysql.sql` (or `.gz`) and `storage-app/...` files.

3. **Restore the DB dump into a scratch database** (not production):
   ```
   mysql -u root -p scratch_db_name < restore_test/db-dumps/mysql.sql
   ```
   (gunzip first if compressed.)

4. **⚠️ CRITICAL — verify AUTO_INCREMENT survived the restore.**
   This app previously had 34 tables silently lose `AUTO_INCREMENT` on
   their `id` column (root cause never determined — see project memory
   `db-missing-auto-increment-incident.md`). A restore is a second path
   by which the same symptom can reappear (mysqldump does preserve
   `AUTO_INCREMENT` in `CREATE TABLE` by default, but do not assume —
   verify every time). Run against the **restored scratch DB**:

   ```sql
   SELECT TABLE_NAME, COLUMN_NAME, EXTRA
   FROM information_schema.COLUMNS
   WHERE TABLE_SCHEMA = 'scratch_db_name'
     AND COLUMN_NAME = 'id'
     AND EXTRA NOT LIKE '%auto_increment%';
   ```

   **This query must return zero rows.** Any row returned = that table's
   `id` column lost AUTO_INCREMENT in the restore — do not promote this
   backup to production until fixed (`ALTER TABLE x MODIFY id BIGINT
   UNSIGNED AUTO_INCREMENT;` per affected table).

5. **Verify files**: spot-check `restore_test/storage-app/` contains
   expected product images / PDF reports, and file counts are in the
   right ballpark vs. the live `storage/app`.

6. **Only after steps 4 and 5 both pass**, restore into production:
   - Take the app offline / maintenance mode.
   - Restore the DB dump into the real database.
   - Copy `storage-app/` contents back into `storage/app`.
   - Re-run the AUTO_INCREMENT query above against the **production**
     database as a final check before taking the app back online.
   - Bring the app back online, smoke-test order creation (exercises
     `id` autoincrement on a live insert) before calling it done.

## Retention

`backup:clean` keeps 7 daily, 4 weekly, 3 monthly backups (spatie
`DefaultStrategy`, see `config/backup.php` → `cleanup.default_strategy`).
Anything older is deleted automatically — don't rely on backups past
that window without pulling a copy off-box first.
