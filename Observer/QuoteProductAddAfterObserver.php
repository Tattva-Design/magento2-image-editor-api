<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;

class QuoteProductAddAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Set project_uuid, project name, custom SKU and custom price to the quote item.
     */
    public function execute(Observer $observer): void
    {
        $items = $observer->getEvent()->getItems();
        if (empty($items)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');

        foreach ($items as $item) {
            $buyRequest = $item->getOptionByCode('info_buyRequest');
            if ($buyRequest) {
                $buyRequestData = json_decode($buyRequest->getValue(), true);
                if (is_array($buyRequestData) && isset($buyRequestData['project_uuid'])) {
                    $uuid = trim((string) $buyRequestData['project_uuid']);
                    $item->setData('project_uuid', $uuid);

                    $product = $item->getProduct();
                    $productSku = $product ? $product->getSku() : '';

                    if ($productSku === 'customisable-product' || $productSku === 'Customise product') {
                        // Fetch project details from database
                        try {
                            $select = $connection->select()
                                ->from($tableName, ['name', 'size', 'frame_type', 'paper_type'])
                                ->where('uuid = ?', $uuid);
                            $projectRow = $connection->fetchRow($select);

                            if ($projectRow) {
                                // 1. Set quote item name to project name
                                if (!empty($projectRow['name'])) {
                                    $item->setName($projectRow['name']);
                                }

                                if ($productSku === 'customisable-product') {
                                    // 2. Build and set custom SKU: customisable-product-size-frame-paper
                                    $sku = 'customisable-product';
                                    if (!empty($projectRow['size'])) {
                                        $sku .= '-' . $projectRow['size'];
                                    }
                                    if (!empty($projectRow['frame_type'])) {
                                        $sku .= '-' . $projectRow['frame_type'];
                                    }
                                    if (!empty($projectRow['paper_type'])) {
                                        $sku .= '-' . $projectRow['paper_type'];
                                    }
                                    // Clean spaces and make it standard format
                                    $sku = str_replace(' ', '-', $sku);
                                    $item->setSku($sku);

                                    // 3. Set custom price to 500 INR
                                    $item->setCustomPrice(500.00);
                                    $item->setOriginalCustomPrice(500.00);
                                    $item->getProduct()->setIsSuperMode(true);
                                }
                            }
                        } catch (\Throwable $e) {
                            // Keep it robust to not break standard add to cart flow
                        }
                    }
                }
            }
        }
    }
}
