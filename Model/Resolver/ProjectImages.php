<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageListProvider;

class ProjectImages implements ResolverInterface
{
    private const DEFAULT_PAGE_SIZE = 20;
    private const MAX_PAGE_SIZE = 100;
    private const ALLOWED_SORT_FIELDS = [
        'CREATED_AT',
        'NAME',
    ];
    private const ALLOWED_SORT_DIRECTIONS = [
        'ASC',
        'DESC',
    ];

    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectImageListProvider $projectImageListProvider
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
        $projectUuid = trim((string) ($args['projectUuid'] ?? ''));
        $currentPage = (int) ($args['currentPage'] ?? 1);
        $pageSize = (int) ($args['pageSize'] ?? self::DEFAULT_PAGE_SIZE);
        $sort = $this->validateSort($args['sort'] ?? []);

        if ($projectUuid === '') {
            throw new GraphQlInputException(__('The "projectUuid" value must be a non-empty string.'));
        }

        if ($currentPage < 1) {
            throw new GraphQlInputException(__('The "currentPage" value must be greater than 0.'));
        }

        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new GraphQlInputException(
                __('The "pageSize" value must be between 1 and %1.', self::MAX_PAGE_SIZE)
            );
        }

        return $this->projectImageListProvider->getList(
            $customerId,
            $projectUuid,
            $currentPage,
            $pageSize,
            $sort
        );
    }

    /**
     * @param array<string, mixed> $sort
     * @return array{field: string, direction: string}
     */
    private function validateSort(array $sort): array
    {
        $field = strtoupper((string) ($sort['field'] ?? 'CREATED_AT'));
        $direction = strtoupper((string) ($sort['direction'] ?? 'DESC'));

        if (!in_array($field, self::ALLOWED_SORT_FIELDS, true)) {
            throw new GraphQlInputException(__('The "sort.field" value must be CREATED_AT or NAME.'));
        }

        if (!in_array($direction, self::ALLOWED_SORT_DIRECTIONS, true)) {
            throw new GraphQlInputException(__('The "sort.direction" value must be ASC or DESC.'));
        }

        return [
            'field' => $field,
            'direction' => $direction,
        ];
    }
}
