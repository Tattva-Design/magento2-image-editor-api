<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Store\Model\StoreManagerInterface;
use TattvaDesign\ImageEditorApi\Model\Util\UuidGenerator;

class ProjectCreator
{
    private const DEFAULT_STATUS = 'active';
    private const ALLOWED_SIZES = ['a2', 'a3', 'a4', 'a5'];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly UuidGenerator $uuidGenerator
    ) {
    }

    /**
     * Create a project for the authenticated customer in the current store.
     */
    public function create(int $customerId, string $name, ?string $description, string $size): array
    {
        if (!in_array($size, self::ALLOWED_SIZES, true)) {
            throw new GraphQlInputException(__('The "size" value must be one of: a2, a3, a4, a5.'));
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
        $storeId = (int) $this->storeManager->getStore()->getId();
        $uuid = $this->uuidGenerator->generate();

        $data = [
            'uuid' => $uuid,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'name' => $name,
            'description' => $description,
            'size' => $size,
            'status' => self::DEFAULT_STATUS,
            'canvas_object' => null,
        ];

        try {
            $connection->insert($tableName, $data);
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to create the project at this time.'));
        }

        $projectId = (int) $connection->lastInsertId($tableName);

        $row = $connection->fetchRow(
            $connection->select()
                ->from($tableName, [
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
                ])
                ->where('id = ?', $projectId)
        );

        if (!$row) {
            throw new GraphQlInputException(__('The project was created, but could not be loaded afterwards.'));
        }

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
