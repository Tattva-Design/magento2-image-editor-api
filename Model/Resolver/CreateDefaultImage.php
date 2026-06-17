<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageUploader;
use TattvaDesign\ImageEditorApi\Model\Validator\ProjectImageInputValidator;

class CreateDefaultImage implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectImageInputValidator $projectImageInputValidator,
        private readonly ProjectImageUploader $projectImageUploader
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
        // Authenticate the customer token / context
        $this->customerContextValidator->getCustomerId($context);
        
        $input = $this->projectImageInputValidator->validateDefaultInput($args['input'] ?? []);

        return $this->projectImageUploader->uploadDefault($input);
    }
}
