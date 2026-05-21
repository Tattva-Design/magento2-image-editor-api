<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Store\Model\StoreManagerInterface;

class ProjectResource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProjectDataMapper $projectDataMapper
    ) {
    }

    public function getConnection()
    {
        return $this->resourceConnection->getConnection();
    }

    public function getTableName(): string
    {
        return $this->resourceConnection->getTableName('tattva_image_editor_project');
    }

    public function getCurrentStoreId(): int
    {
        return (int) $this->storeManager->getStore()->getId();
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectRowById(int $projectId): array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getTableName(), $this->projectDataMapper->getSelectColumns())
                ->where('id = ?', $projectId)
        );

        if (!$row) {
            throw new GraphQlInputException(__('The requested project could not be loaded.'));
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProjectRowByUuid(int $customerId, string $uuid): array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getTableName(), $this->projectDataMapper->getSelectColumns())
                ->where('uuid = ?', $uuid)
                ->where('customer_id = ?', $customerId)
                ->where('store_id = ?', $this->getCurrentStoreId())
        );

        if (!$row) {
            throw new GraphQlInputException(__('The requested project does not exist.'));
        }

        return $row;
    }
}
