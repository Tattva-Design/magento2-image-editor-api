<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Quote\Model\Cart\BuyRequest\BuyRequestBuilder;
use Magento\Framework\DataObject;
use Magento\Quote\Model\Cart\Data\CartItem;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectSuperAttributeMapper;

class BuyRequestBuilderPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry,
        private readonly ProjectSuperAttributeMapper $mapper
    ) {
    }

    /**
     * Inject project_uuid and super_attributes into standard quote buyRequest.
     */
    public function afterBuild(
        BuyRequestBuilder $subject,
        DataObject $result,
        CartItem $cartItem
    ): DataObject {
        $sku = trim((string)$cartItem->getSku());
        if ($sku !== '' && ($uuid = $this->registry->getAndRemove($sku))) {
            $result->setData('project_uuid', $uuid);

            if ($this->mapper->isCustomisable($sku)) {
                $super = array_replace($this->mapper->getSuperAttributes($uuid), (array)($result->getData('super_attribute') ?: []));
                if (!empty($super)) {
                    $result->setData('super_attribute', $super);
                }
            }
        }
        return $result;
    }
}
