<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectDeleter;

class DeleteProject implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectDeleter $projectDeleter
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
        $uuid = trim((string) ($args['uuid'] ?? ''));

        if ($uuid === '') {
            throw new GraphQlInputException(__('The "uuid" value must be a non-empty string.'));
        }

        return $this->projectDeleter->delete($customerId, $uuid);
    }
}
