<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectThumbnailUpdater;

class UpdateProjectThumbnail implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectThumbnailUpdater $projectThumbnailUpdater
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $customerId = $this->customerContextValidator->getCustomerId($context);
        $input = $args['input'] ?? [];

        return $this->projectThumbnailUpdater->updateThumbnail($customerId, $input);
    }
}
