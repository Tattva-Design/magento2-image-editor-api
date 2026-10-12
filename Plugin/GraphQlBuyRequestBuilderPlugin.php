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
        private readonly ProjectSuperAttributeMapper $mapper
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
        $sku = trim((string)($cartItemData['sku'] ?? ''));
        $uuid = trim((string)($cartItemData['project_uuid'] ?? '')) ?: ($sku ? $this->registry->getAndRemove($sku) : null);

        if ($sku !== '' && $uuid) {
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
