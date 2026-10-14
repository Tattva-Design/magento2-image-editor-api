<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Registry;

class CartProjectRegistry
{
    /**
     * Store mapping of SKU to project UUIDs.
     *
     * @var array<string, string[]>
     */
    private array $projectUuids = [];

    /**
     * Register a project UUID for a SKU.
     */
    public function register(string $sku, string $uuid): void
    {
        $clean = strtolower(trim($sku));
        if (!isset($this->projectUuids[$clean])) {
            $this->projectUuids[$clean] = [];
        }
        $this->projectUuids[$clean][] = trim($uuid);
    }

    /**
     * Retrieve the registered project UUID for a SKU without removing it prematurely.
     */
    public function get(string $sku): ?string
    {
        $clean = strtolower(trim($sku));
        if (!empty($this->projectUuids[$clean])) {
            return end($this->projectUuids[$clean]);
        }

        // Fallback for customisable product variants
        foreach ($this->projectUuids as $s => $uuids) {
            if (!empty($uuids) && (str_contains($s, 'customis') || str_contains($s, 'customiz'))) {
                return end($uuids);
            }
        }
        return null;
    }

    /**
     * Retrieve project UUID for backward compatibility with existing calls.
     */
    public function getAndRemove(string $sku): ?string
    {
        return $this->get($sku);
    }

    /**
     * Clear all registered project UUIDs.
     */
    public function clear(): void
    {
        $this->projectUuids = [];
    }
}
