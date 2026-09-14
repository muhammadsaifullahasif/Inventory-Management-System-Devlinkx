<?php

namespace App\Observers;

use App\Services\AuditLogger;
use App\Support\AuditContext;
use Illuminate\Database\Eloquent\Model;

/**
 * Generic change-tracking observer attached to every model listed in
 * config('audit.audited_models') (see AppServiceProvider::boot()) — one
 * class instead of per-model boot() hooks.
 */
class AuditObserver
{
    public function __construct(private AuditLogger $logger, private AuditContext $context)
    {
    }

    public function created(Model $model): void
    {
        // Either a more specific named event is being logged for this same
        // save by AuditLogger::withEvent()/suppress(), or a bulk operation
        // is recording one summary row for everything it touches (see
        // AuditContext::beginBatch() / AuditLogger::logBatch()) — either
        // way, skip the generic "created" row so nothing is recorded twice.
        if ($this->context->inBatch() || $this->context->consumeSuppression($model)) {
            return;
        }

        // A queue job or console command is running (see AppServiceProvider)
        // and hasn't itself opted into AuditLogger::batch() — collect this
        // write into that run's auto-summary instead of writing its own row,
        // so the whole job/command ends up as ONE audit row.
        if ($this->context->inAutoSummary()) {
            $this->context->recordAutoAffected($this->affectedEntry($model, 'created'));

            return;
        }

        $this->logger->log(
            'created',
            $model,
            [],
            $this->logger->filterFields($model, $model->getAttributes())
        );
    }

    public function updated(Model $model): void
    {
        // See created() above for why both checks are here.
        if ($this->context->inBatch() || $this->context->consumeSuppression($model)) {
            return;
        }

        $changes = $model->getChanges();
        unset($changes['updated_at']);

        if (empty($changes)) {
            return;
        }

        if ($this->context->inAutoSummary()) {
            $this->context->recordAutoAffected($this->affectedEntry($model, 'updated'));

            return;
        }

        $old = array_intersect_key($model->getOriginal(), $changes);

        $this->logger->log(
            'updated',
            $model,
            $this->logger->filterFields($model, $old),
            $this->logger->filterFields($model, $changes)
        );
    }

    public function deleted(Model $model): void
    {
        // See created() above for why both checks are here.
        if ($this->context->inBatch() || $this->context->consumeSuppression($model)) {
            return;
        }

        if ($this->context->inAutoSummary()) {
            $this->context->recordAutoAffected($this->affectedEntry($model, 'deleted'));

            return;
        }

        $this->logger->log(
            'deleted',
            $model,
            $this->logger->filterFields($model, $model->getOriginal()),
            []
        );
    }

    /** One entry for a job/command auto-summary row — see logBatch(). */
    private function affectedEntry(Model $model, string $effect): array
    {
        return [
            'type' => $model->getMorphClass(),
            'id' => $model->getKey(),
            'label' => $this->labelFor($model),
            'effect' => $effect,
        ];
    }

    private function labelFor(Model $model): ?string
    {
        foreach (['order_number', 'number', 'sku', 'name', 'title', 'email'] as $field) {
            if (!empty($model->{$field})) {
                return (string) $model->{$field};
            }
        }

        return null;
    }
}
