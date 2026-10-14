<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;
use Magento\Framework\App\ResourceConnection;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectSuperAttributeMapper;

class GetItemsDataPlugin
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProjectSuperAttributeMapper $mapper
    ) {
    }

    /**
     * Override product name and SKU in GraphQL response with custom project values.
     */
    public function afterExecute(GetItemsData $subject, array $result, array $cartItems): array
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('tattva_image_editor_project');

        foreach ($result as $index => &$itemData) {
            $cartItem = $cartItems[$index] ?? null;
            $uuid = $cartItem?->getData('project_uuid');
            $sku = (string)($cartItem?->getProduct()?->getSku() ?: $cartItem?->getSku());

            if (!$uuid || !is_array($itemData) || !$this->mapper->isCustomisable($sku)) {
                continue;
            }

            $itemData['project_uuid'] = $uuid;

            try {
                $name = $connection->fetchOne(
                    $connection->select()->from($table, ['name'])->where('uuid = ?', $uuid)
                );
            } catch (\Throwable) {
                $name = null;
            }

            $name = $name ?: ($cartItem->getName() ?: ($itemData['product']['name'] ?? ''));
            $isConfigurable = ($cartItem->getProductType() === 'configurable'
                || ($cartItem->getProduct() && $cartItem->getProduct()->getTypeId() === 'configurable')
                || $cartItem->getHasChildren());

            if (isset($itemData['product']) && is_array($itemData['product'])) {
                if (!$isConfigurable) {
                    $itemData['product']['sku'] = $cartItem->getSku();
                }
                $itemData['product']['name'] = $name;

                if (isset($itemData['product']['model']) && $itemData['product']['model'] instanceof \Magento\Catalog\Model\Product) {
                    $cloned = clone $itemData['product']['model'];
                    if (!$isConfigurable) {
                        $cloned->setSku($cartItem->getSku());
                    }
                    $cloned->setName($name);
                    $itemData['product']['model'] = $cloned;
                }
            }
        }
        return $result;
    }
}
