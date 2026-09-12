<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogger
{
    public function __construct(private AuditContext $context)
    {
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     * @param  array<string, mixed>  $context  Extra detail merged over whatever the current AuditContext already carries.
     */
    public function log(string $event, ?Model $auditable = null, array $old = [], array $new = [], array $context = []): AuditLog
    {
        return AuditLog::create([
            'uuid' => (string) Str::uuid(),
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'actor_type' => $this->context->actorType,
            'actor_id' => $this->context->actorId,
            'actor_label' => $this->context->actorLabel,
            'ip_address' => $this->context->ipAddress,
            'user_agent' => $this->context->userAgent,
            'url' => $this->context->url,
            'http_method' => $this->context->httpMethod,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'context' => array_merge($this->context->context, $context) ?: null,
            'created_at' => now(),
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    public function filterFields(?Model $model, array $attributes): array
    {
        $excluded = config('audit.excluded_fields', []);
        $hidden = $model?->getHidden() ?? [];

        return array_diff_key($attributes, array_flip(array_merge($excluded, $hidden)));
    }

    /**
     * Wrap a business action (generate label, cancel order, refund, import
     * a product, ...) so it records one audit row named after the action
     * instead of the generic "created"/"updated"/"deleted" row AuditObserver
     * would otherwise write for the same save. $callback should be the thing
     * that actually saves $model (an ->update(...)/->save()/->delete() call,
     * or code that calls it) — AuditObserver checks the suppression flag set
     * here and skips its own write for whichever of those three fires.
     *
     * @param  array<string, mixed>  $context  Extra detail to attach (e.g. carrier, tracking number, reason) beyond the plain before/after diff.
     */
    public function withEvent(string $event, Model $model, callable $callback, array $context = []): mixed
    {
        $this->context->suppressNextUpdateFor($model);

        $existedBefore = $model->exists;
        $originalBefore = $model->getOriginal();

        $result = $callback();

        if ($existedBefore && ! $model->exists) {
            // $callback deleted the model.
            $old = $this->filterFields($model, $originalBefore);
            $new = [];
        } elseif (! $existedBefore && $model->exists) {
            // $callback created the model.
            $old = [];
            $new = $this->filterFields($model, $model->getAttributes());
        } else {
            $changes = $model->getChanges();
            unset($changes['updated_at']);
            // Use the original captured before the callback ran, not
            // $model->getOriginal() now — save() calls syncOriginal() as
            // part of finishing, so by this point getOriginal() would
            // already reflect the NEW values, making old == new.
            $old = $this->filterFields($model, array_intersect_key($originalBefore, $changes));
            $new = $this->filterFields($model, $changes);
        }

        $this->log($event, $model, $old, $new, $context);

        return $result;
    }

    /**
     * Mark $model's next create/update/delete as already accounted for by
     * a batch summary (see logBatch()) so AuditObserver doesn't also write
     * an individual row for it. Call once per record right before you
     * save/delete it inside a bulk loop.
     */
    public function suppress(Model $model): void
    {
        $this->context->suppressNextUpdateFor($model);
    }

    /**
     * Record ONE audit row summarizing a bulk operation (import N products,
     * sync M listings, sync/import orders, ...) instead of one row per
     * affected record. Call suppress() on each record as you process it
     * inside the loop, collect what happened to it into $affected, then
     * call this once after the loop with the full list.
     *
     * @param  array<int, array<string, mixed>>  $affected  One entry per affected record, e.g. ['type' => 'Product', 'id' => 12, 'label' => 'SKU-1', 'effect' => 'created'].
     * @param  array<string, mixed>  $context  Extra detail beyond the affected list (e.g. sales channel, trigger).
     */
    public function logBatch(string $event, array $affected, array $context = [], ?Model $auditable = null): AuditLog
    {
        return $this->log($event, $auditable, [], [], array_merge($context, [
            'affected_count' => count($affected),
            'affected' => $affected,
        ]));
    }

    /**
     * Run $callback with AuditObserver's generic create/update/delete
     * logging switched off for its entire duration — however deeply
     * nested the actual saves happen (e.g. bundle order items created
     * several calls down from an order-sync loop) — then write ONE
     * summary row for whatever $callback reports as affected.
     *
     * $callback receives nothing and must return the affected list itself
     * (build it as you go: push an entry each time you process a record).
     * If $callback throws, the batch still ends cleanly (no logging stays
     * disabled) and the summary row is not written — the exception propagates.
     *
     * @param  callable(): array<int, array<string, mixed>>  $callback
     * @param  array<string, mixed>  $context
     */
    public function batch(string $event, callable $callback, array $context = [], ?Model $auditable = null): mixed
    {
        $this->context->beginBatch();

        try {
            $affected = $callback();
        } finally {
            $this->context->endBatch();
        }

        if (!empty($affected)) {
            $this->logBatch($event, $affected, $context, $auditable);
        }

        return $affected;
    }
}
