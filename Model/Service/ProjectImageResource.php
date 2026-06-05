<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectImageResource
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProjectImageDataMapper $projectImageDataMapper
    ) {
    }

    public function getConnection()
    {
        return $this->resourceConnection->getConnection();
    }

    public function getTableName(): string
    {
        return $this->resourceConnection->getTableName('tattva_image_editor_project_image');
    }

    /**
     * @return array<string, mixed>
     */
    public function getImageRowById(int $imageId): array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getTableName(), $this->projectImageDataMapper->getSelectColumns())
                ->where('id = ?', $imageId)
        );

        if (!$row) {
            throw new GraphQlInputException(__('The requested project image could not be loaded.'));
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function getImageRowByUuid(int $customerId, int $storeId, string $imageUuid): array
    {
        $row = $this->getConnection()->fetchRow(
            $this->getConnection()->select()
                ->from($this->getTableName(), $this->projectImageDataMapper->getSelectColumns())
                ->where('uuid = ?', $imageUuid)
                ->where('customer_id = ?', $customerId)
                ->where('store_id = ?', $storeId)
        );

        if (!$row) {
            throw new GraphQlInputException(__('The requested project image does not exist.'));
        }

        return $row;
    }

    public function doesProjectFileNameExist(int $projectId, string $fileName): bool
    {
        $count = (int) $this->getConnection()->fetchOne(
            $this->getConnection()->select()
                ->from($this->getTableName(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where('project_id = ?', $projectId)
                ->where('file_name = ?', $fileName)
        );

        return $count > 0;
    }

    public function doesProjectOriginalNameExist(int $projectId, string $originalName): bool
    {
        $count = (int) $this->getConnection()->fetchOne(
            $this->getConnection()->select()
                ->from($this->getTableName(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where('project_id = ?', $projectId)
                ->where('original_name = ?', $originalName)
        );

        return $count > 0;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function insertImage(array $data): int
    {
        $connection = $this->getConnection();
        $tableName = $this->getTableName();

        $connection->insert($tableName, $data);

        return (int) $connection->lastInsertId($tableName);
    }

    public function updateImageStorageData(
        int $imageId,
        string $fileName,
        string $filePath,
        ?string $thumbnailPath = null
    ): void {
        $data = [
            'file_name' => $fileName,
            'file_path' => $filePath,
        ];
        if ($thumbnailPath !== null) {
            $data['thumbnail_path'] = $thumbnailPath;
        }

        $this->getConnection()->update(
            $this->getTableName(),
            $data,
            ['id = ?' => $imageId]
        );
    }

    public function deleteImageById(int $imageId): void
    {
        $this->getConnection()->delete($this->getTableName(), ['id = ?' => $imageId]);
    }

    /**
     * @param array<string, string> $sort
     * @return array{totalCount: int, items: array<int, array<string, mixed>>, pageInfo: array<string, mixed>}
     */
    public function getProjectImageList(
        int $projectId,
        int $customerId,
        int $storeId,
        int $currentPage,
        int $pageSize,
        array $sort
    ): array {
        $connection = $this->getConnection();
        
        $whereClause = 'project_id = :project_id AND customer_id = :customer_id AND store_id = :store_id';
        
        $binds = [
            'project_id' => $projectId,
            'customer_id' => $customerId,
            'store_id' => $storeId
        ];

        $totalCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getTableName(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where($whereClause),
            $binds
        );

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;
        if ($totalCount > 0 && $currentPage > $totalPages) {
            throw new GraphQlInputException(
                __('The "currentPage" value %1 is greater than the available pages %2.', $currentPage, $totalPages)
            );
        }

        $offset = ($currentPage - 1) * $pageSize;
        $sortColumn = $sort['column'];
        $sortDirection = $sort['direction'];

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTableName(), $this->projectImageDataMapper->getSelectColumns())
                ->where($whereClause)
                ->order($sortColumn . ' ' . $sortDirection)
                ->order('id ' . ($sortColumn === 'created_at' ? $sortDirection : Select::SQL_DESC))
                ->limit($pageSize, $offset),
            $binds
        );

        return [
            'totalCount' => $totalCount,
            'items' => array_map([$this->projectImageDataMapper, 'mapRow'], $rows),
            'pageInfo' => [
                'pageSize' => $pageSize,
                'currentPage' => $currentPage,
                'totalPages' => $totalPages,
                'hasNextPage' => $currentPage < $totalPages,
                'hasPreviousPage' => $currentPage > 1 && $totalCount > 0,
            ],
        ];
    }

    /**
     * @return array{totalCount: int, items: array<int, array<string, mixed>>, pageInfo: array<string, mixed>}
     */
    public function getDefaultImageList(
        int $currentPage,
        int $pageSize
    ): array {
        $connection = $this->getConnection();
        $whereClause = 'project_id IS NULL AND customer_id IS NULL';

        $totalCount = (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getTableName(), [new \Zend_Db_Expr('COUNT(*)')])
                ->where($whereClause)
        );

        $totalPages = $pageSize > 0 ? (int) ceil($totalCount / $pageSize) : 0;
        if ($totalCount > 0 && $currentPage > $totalPages) {
            throw new GraphQlInputException(
                __('The "currentPage" value %1 is greater than the available pages %2.', $currentPage, $totalPages)
            );
        }

        $offset = ($currentPage - 1) * $pageSize;

        $rows = $connection->fetchAll(
            $connection->select()
                ->from($this->getTableName(), $this->projectImageDataMapper->getSelectColumns())
                ->where($whereClause)
                ->order('id ASC')
                ->limit($pageSize, $offset)
        );

        return [
            'totalCount' => $totalCount,
            'items' => array_map([$this->projectImageDataMapper, 'mapRow'], $rows),
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
