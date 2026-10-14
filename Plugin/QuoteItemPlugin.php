<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Quote\Model\Quote\Item;
use Magento\Catalog\Model\Product;

class QuoteItemPlugin
{
    /**
     * Prevent merging of quote items if they correspond to different project UUIDs.
     */
    public function aroundRepresentProduct(
        Item $subject,
        \Closure $proceed,
        Product $product
    ): bool {
        $result = $proceed($product);
        if ($result) {
            $itemUuid = $subject->getData('project_uuid');

            // Retrieve project_uuid from request custom options
            $buyRequest = $product->getCustomOption('info_buyRequest');
            $requestUuid = null;
            if ($buyRequest) {
                $buyRequestData = json_decode($buyRequest->getValue(), true);
                if (is_array($buyRequestData)) {
                    $requestUuid = $buyRequestData['project_uuid'] ?? null;
                }
            }

            if ($itemUuid !== $requestUuid) {
                return false;
            }
        }
        return $result;
    }
}
