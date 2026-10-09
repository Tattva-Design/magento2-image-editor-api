<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Registry;

class CartProjectRegistry
{
    /**
     * Store mapping of SKU to project UUIDs.
     * Since multiple items of same SKU can be added, we store them in a queue.
     *
     * @var array<string, string[]>
     */
    private array $projectUuids = [];

    /**
     * Register a project UUID for a SKU.
     */
    public function register(string $sku, string $uuid): void
    {
        if (!isset($this->projectUuids[$sku])) {
            $this->projectUuids[$sku] = [];
        }
        $this->projectUuids[$sku][] = $uuid;
    }

    /**
     * Retrieve and dequeue the next registered project UUID for a SKU.
     */
    public function getAndRemove(string $sku): ?string
    {
        if (!empty($this->projectUuids[$sku])) {
            return array_shift($this->projectUuids[$sku]);
        }
        return null;
    }

    /**
     * Clear all registered project UUIDs.
     */
    public function clear(): void
    {
        $this->projectUuids = [];
    }
}
