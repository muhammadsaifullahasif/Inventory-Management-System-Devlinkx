<?php

namespace App\Services\Messaging;

use App\Models\MessageSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Answers "is the team online at this moment?" from the office-hours settings.
 *
 * Hours are per weekday in the configured time zone. A day that is missing or null is closed all day.
 * A day whose entry is present but unreadable counts as OPEN: this decides whether an automatic message
 * goes out to a buyer, so when the settings are unclear the safe answer is to send nothing.
 */
class OfficeHours
{
    public function __construct(private MessageSetting $settings) {}

    public static function current(): self
    {
        return new self(MessageSetting::current());
    }

    public function timezone(): string
    {
        $tz = (string) $this->settings->timezone;

        return in_array($tz, \DateTimeZone::listIdentifiers(), true) ? $tz : (string) config('app.timezone', 'UTC');
    }

    public function isOpen(?CarbonInterface $at = null): bool
    {
        $local = Carbon::instance($at ?? now())->setTimezone($this->timezone());
        $hours = (array) ($this->settings->office_hours ?? []);
        $day = strtolower(substr($local->format('D'), 0, 3));

        if (!array_key_exists($day, $hours) || $hours[$day] === null) {
            return false;
        }

        $open = $this->minutes($hours[$day]['open'] ?? null);
        $close = $this->minutes($hours[$day]['close'] ?? null);
        if ($open === null || $close === null || $close <= $open) {
            return true; // unreadable: do not treat as closed
        }

        $now = $local->hour * 60 + $local->minute;

        return $now >= $open && $now < $close;
    }

    public function isClosed(?CarbonInterface $at = null): bool
    {
        return !$this->isOpen($at);
    }

    private function minutes(mixed $time): ?int
    {
        if (!is_string($time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
