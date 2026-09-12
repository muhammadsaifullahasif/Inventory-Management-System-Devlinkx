<?php

namespace App\Models;

/**
 * Identical shape to AuditLog — see that class. Kept as a separate model
 * (rather than a scope on AuditLog) so it maps cleanly to its own table
 * and can be queried/unioned independently by AuditLogController.
 */
class AuditLogArchive extends AuditLog
{
    protected $table = 'audit_log_archives';
}
