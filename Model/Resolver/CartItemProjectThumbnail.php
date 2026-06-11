<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\UrlInterface;

class CartItemProjectThumbnail implements ResolverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): ?string {
        if (!is_array($value)) {
            return null;
        }

        $quoteItem = $value['model'] ?? null;
        if (!$quoteItem instanceof \Magento\Quote\Model\Quote\Item) {
            return null;
        }

        $projectUuid = $quoteItem->getData('project_uuid');
        if (!$projectUuid) {
            return null;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
        
        $select = $connection->select()
            ->from($tableName, ['thumbnail'])
            ->where('uuid = ?', $projectUuid);
            
        $thumbnail = $connection->fetchOne($select);
        if (!$thumbnail) {
            return null;
        }

        return rtrim($this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
            . '/'
            . ltrim($thumbnail, '/');
    }
}
