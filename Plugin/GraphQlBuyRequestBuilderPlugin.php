<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\QuoteGraphQl\Model\Cart\BuyRequest\BuyRequestBuilder;
use Magento\Framework\DataObject;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectSuperAttributeMapper;

class GraphQlBuyRequestBuilderPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry,
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection,
        private readonly ProjectSuperAttributeMapper $superAttributeMapper
    ) {
    }

    /**
     * Inject project_uuid and super_attributes into GraphQL buyRequest.
     */
    public function afterBuild(
        BuyRequestBuilder $subject,
        DataObject $result,
        array $cartItemData
    ): DataObject {
        $sku = isset($cartItemData['sku']) ? trim((string)$cartItemData['sku']) : '';
        $uuid = isset($cartItemData['project_uuid']) ? trim((string)$cartItemData['project_uuid']) : '';

        if ($sku !== '') {
            if ($uuid === '') {
                $uuid = $this->registry->getAndRemove($sku);
            }
            if ($uuid !== '') {
                $result->setData('project_uuid', $uuid);

                if ($this->superAttributeMapper->isCustomisableProductSku($sku)) {
                    $connection = $this->resourceConnection->getConnection();
                    $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
                    try {
                        $select = $connection->select()
                            ->from($tableName, ['size', 'frame_type', 'paper_type'])
                            ->where('uuid = ?', $uuid);
                        $projectRow = $connection->fetchRow($select);
                        if ($projectRow) {
                            $superAttributes = $this->superAttributeMapper->mapSuperAttributes(
                                $projectRow['size'] ?? null,
                                $projectRow['frame_type'] ?? null,
                                $projectRow['paper_type'] ?? null
                            );

                            $existingSuperAttribute = $result->getData('super_attribute') ?: [];
                            $superAttribute = is_array($existingSuperAttribute) ? $existingSuperAttribute : [];

                            foreach ($superAttributes as $attrId => $optId) {
                                if (!isset($superAttribute[$attrId])) {
                                    $superAttribute[$attrId] = $optId;
                                }
                            }

                            if (!empty($superAttribute)) {
                                $result->setData('super_attribute', $superAttribute);
                            }
                        }
                    } catch (\Throwable $e) {
                        // Keep robust
                    }
                }
            }
        }
        return $result;
    }
}
