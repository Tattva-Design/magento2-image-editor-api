<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;

class AddProductsToCartPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry
    ) {
    }

    /**
     * Intercept resolve to capture project_uuid inputs and populate Registry.
     */
    public function beforeResolve(
        $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): void {
        $cartItems = $args['cartItems'] ?? [];
        foreach ($cartItems as $item) {
            $sku = $item['sku'] ?? '';
            $projectUuid = $item['project_uuid'] ?? '';
            if ($sku !== '' && $projectUuid !== '') {
                $this->registry->register(trim($sku), trim($projectUuid));
            }
        }
    }

    /**
     * Clear registry after resolution is completed.
     */
    public function afterResolve(
        $subject,
        $result
    ) {
        $this->registry->clear();
        return $result;
    }
}
