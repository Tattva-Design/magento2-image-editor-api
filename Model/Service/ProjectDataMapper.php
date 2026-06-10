<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class ProjectDataMapper
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager
    ) {
    }

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
            'width',
            'height',
            'status',
            'product_sku',
            'canvas_object',
            'thumbnail',
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
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
            'status' => (string) $row['status'],
            'productSku' => isset($row['product_sku']) ? (string) $row['product_sku'] : null,
            'canvasObject' => $row['canvas_object'] !== null ? (string) $row['canvas_object'] : null,
            'thumbnailUrl' => !empty($row['thumbnail']) ? $this->buildFileUrl((string) $row['thumbnail']) : null,
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
