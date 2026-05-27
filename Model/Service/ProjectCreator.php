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

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly UuidGenerator $uuidGenerator,
        private readonly ProjectDataMapper $projectDataMapper,
        private readonly ProjectResource $projectResource
    ) {
    }

    /**
     * Create a project for the authenticated customer in the current store.
     *
     * @param array{name: string, description: ?string, size: string, width: ?int, height: ?int} $input
     */
    public function create(int $customerId, array $input): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
        $storeId = (int) $this->storeManager->getStore()->getId();
        $uuid = $this->uuidGenerator->generate();

        $data = [
            'uuid' => $uuid,
            'customer_id' => $customerId,
            'store_id' => $storeId,
            'name' => $input['name'],
            'description' => $input['description'],
            'size' => $input['size'],
            'width' => $input['width'] ?? null,
            'height' => $input['height'] ?? null,
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

        return $this->projectDataMapper->mapRow(
            $this->projectResource->getProjectRowById($projectId)
        );
    }
}
