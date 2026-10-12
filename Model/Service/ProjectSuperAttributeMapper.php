<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;

class ProjectSuperAttributeMapper
{
    private const SIZES = ['a3' => 6, 'a4' => 7, 'a5' => 8];
    private const FRAMES = ['with frame' => 4, 'without frame' => 5];
    private const PAPERS = ['art paper' => 9, 'canvas' => 10];

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Check if a SKU belongs to a customizable product (handles hyphens, underscores, casing).
     */
    public function isCustomisable(string $sku): bool
    {
        $clean = strtolower(str_replace(['_', '-'], ' ', trim($sku)));
        return str_contains($clean, 'customis') || str_contains($clean, 'customiz');
    }

    /**
     * Fetch project options by UUID and map them to super_attribute [attribute_id => option_id].
     *
     * @return array<int, int>
     */
    public function getSuperAttributes(string $uuid): array
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('tattva_image_editor_project');
            $row = $connection->fetchRow(
                $connection->select()->from($table, ['size', 'frame_type', 'paper_type'])->where('uuid = ?', $uuid)
            );
            if (!$row) {
                return [];
            }

            $size = strtolower(str_replace(['_', '-', ' '], '', (string)($row['size'] ?? '')));
            $frame = strtolower(str_replace(['_', '-'], ' ', (string)($row['frame_type'] ?? '')));
            $paper = strtolower(str_replace(['_', '-'], ' ', (string)($row['paper_type'] ?? '')));

            return [
                138 => self::SIZES[$size] ?? 7,   // Size (138): default A4 (7)
                137 => self::FRAMES[$frame] ?? 5, // Frame (137): default Without Frame (5)
                139 => self::PAPERS[$paper] ?? 9, // Paper (139): default Art Paper (9)
            ];
        } catch (\Throwable) {
            return [];
        }
    }
}
