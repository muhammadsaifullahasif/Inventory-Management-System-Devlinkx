<?php

namespace App\Support;

/**
 * Request/job/command-scoped "who is acting right now" holder. Bound as a
 * singleton so CaptureAuditContext (HTTP), the queue before/after listeners,
 * and auth listeners can all populate it, and AuditLogger just reads it at
 * write time instead of every call site re-deriving actor/ip/UA.
 */
class AuditContext
{
    public string $actorType = 'system';

    public ?int $actorId = null;

    public ?string $actorLabel = null;

    public ?string $ipAddress = null;

    public ?string $userAgent = null;

    public ?string $url = null;

    public ?string $httpMethod = null;

    /** @var array<string, mixed> */
    public array $context = [];

    /**
     * spl_object_id() keys for model instances whose next AuditObserver
     * "updated" firing should be skipped — set by AuditLogger::withEvent()
     * when calling code is about to log a more specific named event
     * ("label_generated", "order_cancelled") for the same save, so the
     * generic "updated" row isn't also written.
     *
     * @var array<int, true>
     */
    private array $suppressedModels = [];

    public function suppressNextUpdateFor(object $model): void
    {
        $this->suppressedModels[spl_object_id($model)] = true;
    }

    public function consumeSuppression(object $model): bool
    {
        $key = spl_object_id($model);

        if (isset($this->suppressedModels[$key])) {
            unset($this->suppressedModels[$key]);

            return true;
        }

        return false;
    }

    /**
     * Depth counter for AuditLogger::batch() — while > 0, AuditObserver
     * skips every create/update/delete row it would otherwise write,
     * however deeply nested (e.g. bundle order items created several
     * calls down from a bulk order-sync loop), because the calling code
     * is building one summary row for the whole operation instead. A
     * counter (not a bool) so nested batch() calls don't let an inner
     * batch's end() re-enable logging while the outer one is still open.
     */
    private int $batchDepth = 0;

    public function beginBatch(): void
    {
        $this->batchDepth++;
    }

    public function endBatch(): void
    {
        $this->batchDepth = max(0, $this->batchDepth - 1);
    }

    public function inBatch(): bool
    {
        return $this->batchDepth > 0;
    }

    public function reset(): void
    {
        $this->actorType = 'system';
        $this->actorId = null;
        $this->actorLabel = null;
        $this->ipAddress = null;
        $this->userAgent = null;
        $this->url = null;
        $this->httpMethod = null;
        $this->context = [];
        $this->batchDepth = 0;
    }
}
