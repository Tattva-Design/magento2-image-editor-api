<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;
use Magento\Framework\App\ResourceConnection;

class GetItemsDataPlugin
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Override product name and SKU in GraphQL response with the custom quote item values.
     *
     * @param GetItemsData $subject
     * @param array $result
     * @param array $cartItems
     * @return array
     */
    public function afterExecute(GetItemsData $subject, array $result, array $cartItems): array
    {
        $connection = $this->resourceConnection->getConnection();
        $tableName = $this->resourceConnection->getTableName('tattva_image_editor_project');

        foreach ($result as $index => &$itemData) {
            if (is_array($itemData) && isset($cartItems[$index])) {
                $cartItem = $cartItems[$index];
                $projectUuid = $cartItem->getData('project_uuid');
                if ($projectUuid) {
                    $itemData['project_uuid'] = $projectUuid;
                    $product = $cartItem->getProduct();
                    $productSku = $product ? $product->getSku() : '';

                    if ($productSku === 'customisable-product' || $productSku === 'Customise product') {
                        if (isset($itemData['product']) && is_array($itemData['product'])) {
                            $customSku = $cartItem->getOrigData('sku') ?: ($cartItem->getData('sku') ?: $cartItem->getSku());
                            
                            // Query the project name from tattva_image_editor_project database table
                            $customName = null;
                            try {
                                $select = $connection->select()
                                    ->from($tableName, ['name'])
                                    ->where('uuid = ?', $projectUuid);
                                $customName = $connection->fetchOne($select);
                            } catch (\Throwable $e) {
                                // Fallback in case of database errors
                            }

                            if (!$customName) {
                                $customName = $cartItem->getOrigData('name') ?: ($cartItem->getData('name') ?: $cartItem->getName());
                            }

                            if ($productSku === 'customisable-product') {
                                $itemData['product']['sku'] = $customSku;
                            }
                            $itemData['product']['name'] = $customName;
                            
                            if (isset($itemData['product']['model']) && $itemData['product']['model'] instanceof \Magento\Catalog\Model\Product) {
                                $clonedProduct = clone $itemData['product']['model'];
                                if ($productSku === 'customisable-product') {
                                    $clonedProduct->setSku($customSku);
                                }
                                $clonedProduct->setName($customName);
                                $itemData['product']['model'] = $clonedProduct;
                            }
                        }
                    }
                }
            }
        }
        return $result;
    }
}
