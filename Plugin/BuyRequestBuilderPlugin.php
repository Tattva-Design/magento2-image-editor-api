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
        private readonly CartProjectRegistry $registry
    ) {
    }

    /**
     * Inject project_uuid into buyRequest array.
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
            }
        }
        return $result;
    }
}
