<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    /**
     * How often last_used_at is actually written.
     */
    public const LAST_USED_RESOLUTION_SECONDS = 60;

    /**
     * Sanctum writes last_used_at on every request. The admin panel fires
     * several requests at once with one token, so those writes queue up on
     * the same row and, on the busy shared server, stretched requests to
     * 5–10 s. Within a minute of the last write the touch is skipped; any
     * other change is saved as usual.
     *
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        if ($this->exists && array_keys($this->getDirty()) === ['last_used_at'] && $this->usedRecently()) {
            $this->syncOriginalAttribute('last_used_at');

            return true;
        }

        return parent::save($options);
    }

    private function usedRecently(): bool
    {
        $stored = $this->getOriginal('last_used_at');

        return $stored !== null && $stored->greaterThan(now()->subSeconds(self::LAST_USED_RESOLUTION_SECONDS));
    }
}
