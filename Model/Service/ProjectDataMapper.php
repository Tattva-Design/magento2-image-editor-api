<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

class ProjectDataMapper
{
    /**
     * @return string[]
     */
    public function getSelectColumns(): array
    {
        return [
            'id',
            'uuid',
            'customer_id',
            'store_id',
            'name',
            'description',
            'size',
            'status',
            'canvas_object',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'uuid' => (string) $row['uuid'],
            'customerId' => (int) $row['customer_id'],
            'storeId' => (int) $row['store_id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'size' => (string) $row['size'],
            'status' => (string) $row['status'],
            'canvasObject' => $row['canvas_object'] !== null ? (string) $row['canvas_object'] : null,
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }
}
