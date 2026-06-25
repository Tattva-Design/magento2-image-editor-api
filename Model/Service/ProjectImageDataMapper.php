<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProjectImageDataMapper
{
    public function __construct(private readonly StoreManagerInterface $storeManager)
    {
    }

    /**
     * @return string[]
     */
    public function getSelectColumns(): array
    {
        return [
            'id',
            'uuid',
            'project_id',
            'customer_id',
            'store_id',
            'status',
            'file_name',
            'original_name',
            'file_path',
            'thumbnail_path',
            'mime_type',
            'extension',
            'size_bytes',
            'width',
            'height',
            'description',
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
            'projectId' => (int) $row['project_id'],
            'customerId' => (int) $row['customer_id'],
            'storeId' => (int) $row['store_id'],
            'status' => (string) $row['status'],
            'fileName' => (string) $row['file_name'],
            'originalName' => (string) $row['original_name'],
            'filePath' => (string) $row['file_path'],
            'imageUrl' => $this->buildFileUrl((string) $row['file_path']),
            'thumbnailUrl' => !empty($row['thumbnail_path']) ? $this->buildFileUrl((string) $row['thumbnail_path']) : null,
            'mimeType' => (string) $row['mime_type'],
            'extension' => (string) $row['extension'],
            'sizeBytes' => (int) $row['size_bytes'],
            'width' => $row['width'] !== null ? (int) $row['width'] : null,
            'height' => $row['height'] !== null ? (int) $row['height'] : null,
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'isDefault' => $row['customer_id'] === null,
            'createdAt' => (string) $row['created_at'],
            'updatedAt' => (string) $row['updated_at'],
        ];
    }

    private function buildFileUrl(string $filePath): string
    {
        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
            . '/'
            . ltrim($filePath, '/');
    }
}
