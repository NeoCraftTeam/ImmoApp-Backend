<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Extracts visibility toggle logic from the Ad model.
 */
trait HasVisibility
{
    public function toggleVisibility(): void
    {
        $this->update(['is_visible' => !$this->is_visible]);
    }

    public function hide(): void
    {
        $this->update(['is_visible' => false]);
    }

    public function show(): void
    {
        $this->update(['is_visible' => true]);
    }
}
