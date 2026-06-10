<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\QuoteGraphQl\Model\Cart\BuyRequest\BuyRequestBuilder;
use Magento\QuoteGraphQl\Model\Cart\BuyRequest\CartItemData;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;

class BuyRequestBuilderPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry
    ) {
    }

    /**
     * Inject project_uuid into buyRequest array.
     */
    public function afterBuild(
        BuyRequestBuilder $subject,
        array $result,
        CartItemData $cartItemData
    ): array {
        $sku = trim($cartItemData->getSku());
        $uuid = $this->registry->getAndRemove($sku);
        if ($uuid) {
            $result['project_uuid'] = $uuid;
        }
        return $result;
    }
}
