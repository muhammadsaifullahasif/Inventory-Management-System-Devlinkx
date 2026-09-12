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

        $this->logger->log(
            'deleted',
            $model,
            $this->logger->filterFields($model, $model->getOriginal()),
            []
        );
    }
}
