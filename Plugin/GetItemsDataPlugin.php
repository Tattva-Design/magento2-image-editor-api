<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\QuoteGraphQl\Model\CartItem\GetItemsData;

class GetItemsDataPlugin
{
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
        foreach ($result as $index => &$itemData) {
            if (is_array($itemData) && isset($cartItems[$index])) {
                $cartItem = $cartItems[$index];
                $projectUuid = $cartItem->getData('project_uuid');
                if ($projectUuid) {
                    if (isset($itemData['product']) && is_array($itemData['product'])) {
                        $customSku = $cartItem->getOrigData('sku') ?: ($cartItem->getData('sku') ?: $cartItem->getSku());
                        $customName = $cartItem->getOrigData('name') ?: ($cartItem->getData('name') ?: $cartItem->getName());
                        $itemData['product']['sku'] = $customSku;
                        $itemData['product']['name'] = $customName;
                        
                        if (isset($itemData['product']['model']) && $itemData['product']['model'] instanceof \Magento\Catalog\Model\Product) {
                            $clonedProduct = clone $itemData['product']['model'];
                            $clonedProduct->setSku($customSku);
                            $clonedProduct->setName($customName);
                            $itemData['product']['model'] = $clonedProduct;
                        }
                    }
                }
            }
        }
        return $result;
    }
}
