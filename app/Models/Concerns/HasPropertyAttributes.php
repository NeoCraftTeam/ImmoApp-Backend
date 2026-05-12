<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Manages JSON-stored property attributes on a model.
 */
trait HasPropertyAttributes
{
    public function hasPropertyAttribute(string $attribute): bool
    {
        $attributes = $this->getAttribute('attributes') ?? [];

        return in_array($attribute, $attributes, true);
    }

    /**
     * @param  array<string>  $newAttributes
     */
    public function addPropertyAttributes(array $newAttributes): void
    {
        $currentAttributes = $this->getAttribute('attributes') ?? [];
        $this->update([
            'attributes' => array_unique(array_merge($currentAttributes, $newAttributes)),
        ]);
    }

    /**
     * @param  array<string>  $attributesToRemove
     */
    public function removePropertyAttributes(array $attributesToRemove): void
    {
        $currentAttributes = $this->getAttribute('attributes') ?? [];
        $this->update([
            'attributes' => array_values(array_diff($currentAttributes, $attributesToRemove)),
        ]);
    }
}
