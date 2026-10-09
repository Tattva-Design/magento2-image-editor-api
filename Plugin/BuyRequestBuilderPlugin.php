<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Quote\Model\Cart\BuyRequest\BuyRequestBuilder;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Cart\Data\CartItem;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;

class BuyRequestBuilderPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry,
        private readonly \Magento\Framework\App\ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Inject project_uuid and super_attributes into buyRequest array.
     */
    public function afterBuild(
        BuyRequestBuilder $subject,
        DataObject $result,
        CartItem $cartItem
    ): DataObject {
        $sku = trim($cartItem->getSku());
        if ($sku !== '') {
            $uuid = $this->registry->getAndRemove($sku);
            if ($uuid) {
                $result->setData('project_uuid', $uuid);

                if ($sku === 'Customise product' || $sku === 'customisable-product') {
                    $connection = $this->resourceConnection->getConnection();
                    $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');
                    try {
                        $select = $connection->select()
                            ->from($tableName, ['size', 'frame_type', 'paper_type'])
                            ->where('uuid = ?', $uuid);
                        $projectRow = $connection->fetchRow($select);
                        if ($projectRow) {
                            $size = isset($projectRow['size']) ? strtolower(trim((string)$projectRow['size'])) : '';
                            $frameType = isset($projectRow['frame_type']) ? strtolower(trim((string)$projectRow['frame_type'])) : '';
                            $paperType = isset($projectRow['paper_type']) ? strtolower(trim((string)$projectRow['paper_type'])) : '';

                            // Map sizes: A3 -> 6, A4 -> 7, A5 -> 8
                            $sizeMap = ['a3' => 6, 'a4' => 7, 'a5' => 8];
                            $sizeOptionId = $sizeMap[$size] ?? 7; // Fallback to A4 (7)

                            // Map frame type: with frame -> 4, without frame -> 5
                            $frameMap = ['with frame' => 4, 'without frame' => 5];
                            $frameOptionId = $frameMap[$frameType] ?? 5; // Fallback to Without Frame (5)

                            // Map paper type: art paper -> 9, canvas -> 10
                            $paperMap = ['art paper' => 9, 'canvas' => 10];
                            $paperOptionId = $paperMap[$paperType] ?? 9; // Fallback to Art Paper (9)

                            $existingSuperAttribute = $result->getData('super_attribute') ?: [];
                            $superAttribute = is_array($existingSuperAttribute) ? $existingSuperAttribute : [];

                            if (!isset($superAttribute[138]) && $sizeOptionId !== null) {
                                $superAttribute[138] = $sizeOptionId;
                            }
                            if (!isset($superAttribute[137]) && $frameOptionId !== null) {
                                $superAttribute[137] = $frameOptionId;
                            }
                            if (!isset($superAttribute[139]) && $paperOptionId !== null) {
                                $superAttribute[139] = $paperOptionId;
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
