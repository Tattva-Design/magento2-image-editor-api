<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageResource;

class DefaultImages implements ResolverInterface
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 100;

    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectImageResource $projectImageResource
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
        $this->customerContextValidator->getCustomerId($context);
        $currentPage = (int) ($args['currentPage'] ?? 1);
        $pageSize = (int) ($args['pageSize'] ?? self::DEFAULT_PAGE_SIZE);

        if ($currentPage < 1) {
            throw new GraphQlInputException(__('The "currentPage" value must be greater than 0.'));
        }

        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new GraphQlInputException(
                __('The "pageSize" value must be between 1 and %1.', self::MAX_PAGE_SIZE)
            );
        }

        return $this->projectImageResource->getDefaultImageList($currentPage, $pageSize);
    }
}
