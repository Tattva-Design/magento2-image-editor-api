<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\DB\Select;

class ProjectImageListProvider
{
    /**
     * @var array<string, string>
     */
    private const SORT_FIELD_MAP = [
        'CREATED_AT' => 'created_at',
        'NAME' => 'original_name',
    ];

    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly ProjectImageResource $projectImageResource
    ) {
    }

    /**
     * @param array<string, mixed> $sort
     * @return array{totalCount: int, items: array<int, array<string, mixed>>, pageInfo: array<string, mixed>}
     */
    public function getList(
        int $customerId,
        string $projectUuid,
        int $currentPage,
        int $pageSize,
        array $sort
    ): array {
        $projectRow = $this->projectResource->getProjectRowByUuid($customerId, $projectUuid);
        $sortField = (string) ($sort['field'] ?? 'CREATED_AT');
        $sortDirection = strtoupper((string) ($sort['direction'] ?? Select::SQL_DESC));

        return $this->projectImageResource->getProjectImageList(
            (int) $projectRow['id'],
            $customerId,
            (int) $projectRow['store_id'],
            $currentPage,
            $pageSize,
            [
                'column' => self::SORT_FIELD_MAP[$sortField] ?? self::SORT_FIELD_MAP['CREATED_AT'],
                'direction' => $sortDirection === Select::SQL_ASC ? Select::SQL_ASC : Select::SQL_DESC,
            ]
        );
    }
}
