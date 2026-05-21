<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectCreator;

class CreateProject implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectCreator $projectCreator
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

        $name = trim((string) ($input['name'] ?? ''));
        $size = strtolower(trim((string) ($input['size'] ?? '')));
        $description = isset($input['description']) ? trim((string) $input['description']) : null;

        if ($name === '') {
            throw new GraphQlInputException(__('The "name" value must be a non-empty string.'));
        }

        if ($size === '') {
            throw new GraphQlInputException(__('The "size" value must be a non-empty string.'));
        }

        return $this->projectCreator->create(
            $customerId,
            $name,
            $description !== '' ? $description : null,
            $size
        );
    }
}
