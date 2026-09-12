<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\AuditLogArchive;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AuditLogController extends Controller
{
    private const SORTABLE_COLUMNS = ['created_at', 'event', 'actor_label', 'auditable_type'];

    private const COLUMNS = [
        'id', 'uuid', 'event', 'auditable_type', 'auditable_id',
        'actor_type', 'actor_id', 'actor_label', 'ip_address', 'created_at',
    ];

    public function __construct()
    {
        $this->middleware(PermissionMiddleware::using('view audit-logs'));
    }

    public function index(Request $request): View
    {
        $sortBy = in_array($request->query('sort_by'), self::SORTABLE_COLUMNS, true)
            ? $request->query('sort_by')
            : 'created_at';
        $sortOrder = $request->query('sort_order') === 'asc' ? 'asc' : 'desc';

        $filters = $request->only(['date_from', 'date_to', 'event', 'actor_type', 'auditable_type', 'actor', 'actor_id']);

        $hot = $this->filtered(AuditLog::query(), $filters);
        $cold = $this->filtered(AuditLogArchive::query(), $filters);

        $logs = $hot->unionAll($cold)
            ->orderBy($sortBy, $sortOrder)
            ->paginate(25)
            ->withQueryString();

        // Event names are open-ended (business actions like "label_generated"
        // get their own name — see AuditLogger::withEvent()), so the filter
        // dropdown is populated from what's actually been recorded rather
        // than a fixed list. Cached briefly since this changes rarely.
        $eventTypes = Cache::remember('audit-logs.event-types', 300, function () {
            return AuditLog::query()->distinct()->pluck('event')
                ->merge(AuditLogArchive::query()->distinct()->pluck('event'))
                ->unique()
                ->sort()
                ->values();
        });

        $actorTypes = ['user', 'api', 'webhook', 'queue', 'console', 'system'];

        $users = User::orderBy('name')->get(['id', 'name']);

        return view('audit-logs.index', compact('logs', 'filters', 'sortBy', 'sortOrder', 'eventTypes', 'actorTypes', 'users'));
    }

    public function show(string $uuid): View
    {
        $log = AuditLog::where('uuid', $uuid)->first()
            ?? AuditLogArchive::where('uuid', $uuid)->firstOrFail();

        return view('audit-logs.show', compact('log'));
    }

    /** @param  array<string, mixed>  $filters */
    private function filtered(Builder $query, array $filters): Builder
    {
        $query->select(self::COLUMNS);

        if (! empty($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        if (! empty($filters['event'])) {
            $query->where('event', $filters['event']);
        }

        if (! empty($filters['actor_type'])) {
            $query->where('actor_type', $filters['actor_type']);
        }

        if (! empty($filters['actor_id'])) {
            $query->where('actor_id', $filters['actor_id']);
        }

        if (! empty($filters['auditable_type'])) {
            $query->where('auditable_type', $filters['auditable_type']);
        }

        if (! empty($filters['actor'])) {
            $query->where(function (Builder $q) use ($filters) {
                $q->where('actor_label', 'like', "%{$filters['actor']}%")
                    ->orWhere('ip_address', 'like', "%{$filters['actor']}%");
            });
        }

        return $query;
    }
}
