<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Store\Model\StoreManagerInterface;

class ProjectListProvider
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly ProjectDataMapper $projectDataMapper
    ) {
    }

    /**
     * Return the current customer's projects, ordered by newest first.
     */
    public function getList(int $customerId, int $currentPage, int $pageSize, string $status = 'active'): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
        $storeId = (int) $this->storeManager->getStore()->getId();

        $countSelect = $connection->select()
            ->from($tableName, [new \Zend_Db_Expr('COUNT(*)')])
            ->where('customer_id = ?', $customerId)
            ->where('store_id = ?', $storeId);

        if ($status !== 'all') {
            $countSelect->where('status = ?', $status);
        }

        $totalCount = (int) $connection->fetchOne($countSelect);

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;

        if ($totalCount > 0 && $currentPage > $totalPages) {
            throw new GraphQlInputException(
                __('The "currentPage" value %1 is greater than the available pages %2.', $currentPage, $totalPages)
            );
        }

        $offset = ($currentPage - 1) * $pageSize;

        $select = $connection->select()
            ->from($tableName, $this->projectDataMapper->getSelectColumns())
            ->where('customer_id = ?', $customerId)
            ->where('store_id = ?', $storeId);

        if ($status !== 'all') {
            $select->where('status = ?', $status);
        }

        $select->order('created_at ' . Select::SQL_DESC)
            ->order('id ' . Select::SQL_DESC)
            ->limit($pageSize, $offset);

        $rows = $connection->fetchAll($select);

        return [
            'totalCount' => $totalCount,
            'items' => array_map([$this->projectDataMapper, 'mapRow'], $rows),
            'pageInfo' => [
                'pageSize' => $pageSize,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'hasNextPage' => $currentPage < $totalPages,
                'hasPreviousPage' => $currentPage > 1 && $totalCount > 0,
            ],
        ];
    }
}
