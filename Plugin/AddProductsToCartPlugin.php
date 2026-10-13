<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectSuperAttributeMapper;

class AddProductsToCartPlugin
{
    public function __construct(
        private readonly CartProjectRegistry $registry,
        private readonly ProjectSuperAttributeMapper $mapper
    ) {
    }

    /**
     * Intercept resolve to capture project_uuid inputs and inject selected_options into cartItems.
     *
     * @return array
     */
    public function beforeResolve(
        $subject,
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        if (!isset($args['cartItems']) || !is_array($args['cartItems'])) {
            return [$field, $context, $info, $value, $args];
        }

        foreach ($args['cartItems'] as &$item) {
            $sku = trim((string)($item['sku'] ?? ''));
            $projectUuid = trim((string)($item['project_uuid'] ?? ''));

            if ($sku !== '' && $projectUuid !== '') {
                $this->registry->register($sku, $projectUuid);

                if ($this->mapper->isCustomisable($sku)) {
                    $superAttrs = $this->mapper->getSuperAttributes($projectUuid);
                    if (!empty($superAttrs)) {
                        $selectedOptions = (array)($item['selected_options'] ?? []);
                        foreach ($superAttrs as $attrId => $optId) {
                            $selectedOptions[] = base64_encode("configurable/{$attrId}/{$optId}");
                        }
                        $item['selected_options'] = array_values(array_unique($selectedOptions));
                    }
                }
            }
        }
        unset($item);

        return [$field, $context, $info, $value, $args];
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
