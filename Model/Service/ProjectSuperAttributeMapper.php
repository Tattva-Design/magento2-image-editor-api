<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\ResourceConnection;

class ProjectSuperAttributeMapper
{
    private const DEFAULT_SIZES = ['a3' => 6, 'a4' => 7, 'a5' => 8];
    private const DEFAULT_FRAMES = ['with frame' => 4, 'without frame' => 5];
    private const DEFAULT_PAPERS = ['art paper' => 9, 'canvas' => 10];

    private ?array $resolvedConfig = null;

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

            $config = $this->getResolvedConfig();

            $sizeAttrId = $config['attrs']['size'] ?? 138;
            $frameAttrId = $config['attrs']['frame'] ?? 137;
            $paperAttrId = $config['attrs']['paper'] ?? 139;

            $sizeOptId = $config['options']['size'][$size] ?? (self::DEFAULT_SIZES[$size] ?? 7);
            $frameOptId = $config['options']['frame'][$frame] ?? (self::DEFAULT_FRAMES[$frame] ?? 5);
            $paperOptId = $config['options']['paper'][$paper] ?? (self::DEFAULT_PAPERS[$paper] ?? 9);

            return [
                $sizeAttrId => $sizeOptId,
                $frameAttrId => $frameOptId,
                $paperAttrId => $paperOptId,
            ];
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Resolve attribute and option IDs from database, caching during execution.
     */
    private function getResolvedConfig(): array
    {
        if ($this->resolvedConfig !== null) {
            return $this->resolvedConfig;
        }

        $config = [
            'attrs' => [
                'size' => 138,
                'frame' => 137,
                'paper' => 139,
            ],
            'options' => [
                'size' => self::DEFAULT_SIZES,
                'frame' => self::DEFAULT_FRAMES,
                'paper' => self::DEFAULT_PAPERS,
            ],
        ];

        try {
            $connection = $this->resourceConnection->getConnection();
            $eavAttrTable = $this->resourceConnection->getTableName('eav_attribute');

            // Find attributes
            $select = $connection->select()
                ->from($eavAttrTable, ['attribute_id', 'attribute_code'])
                ->where('entity_type_id = ?', 4)
                ->where('attribute_id IN (?) OR attribute_code IN (?)', [137, 138, 139], ['size', 'frame_type', 'paper_type', 'frame', 'paper']);
            $attrRows = $connection->fetchAll($select);

            foreach ($attrRows as $attr) {
                $attrId = (int)$attr['attribute_id'];
                $code = (string)$attr['attribute_code'];

                if ($attrId === 138 || $code === 'size') {
                    $config['attrs']['size'] = $attrId;
                } elseif ($attrId === 137 || in_array($code, ['frame_type', 'frame'], true)) {
                    $config['attrs']['frame'] = $attrId;
                } elseif ($attrId === 139 || in_array($code, ['paper_type', 'paper'], true)) {
                    $config['attrs']['paper'] = $attrId;
                }
            }

            // Find option IDs
            $optionTable = $this->resourceConnection->getTableName('eav_attribute_option');
            $optionValTable = $this->resourceConnection->getTableName('eav_attribute_option_value');

            $allAttrIds = array_values($config['attrs']);
            $selectOpts = $connection->select()
                ->from(['o' => $optionTable], ['o.attribute_id', 'o.option_id'])
                ->join(['v' => $optionValTable], 'o.option_id = v.option_id', ['val' => 'LOWER(TRIM(v.value))'])
                ->where('o.attribute_id IN (?)', $allAttrIds);
            $optRows = $connection->fetchAll($selectOpts);

            foreach ($optRows as $opt) {
                $attrId = (int)$opt['attribute_id'];
                $optId = (int)$opt['option_id'];
                $val = (string)$opt['val'];

                if ($attrId === $config['attrs']['size']) {
                    if (str_contains($val, 'a3')) {
                        $config['options']['size']['a3'] = $optId;
                    } elseif (str_contains($val, 'a4')) {
                        $config['options']['size']['a4'] = $optId;
                    } elseif (str_contains($val, 'a5')) {
                        $config['options']['size']['a5'] = $optId;
                    }
                } elseif ($attrId === $config['attrs']['frame']) {
                    if (str_contains($val, 'without')) {
                        $config['options']['frame']['without frame'] = $optId;
                    } elseif (str_contains($val, 'with')) {
                        $config['options']['frame']['with frame'] = $optId;
                    }
                } elseif ($attrId === $config['attrs']['paper']) {
                    if (str_contains($val, 'art')) {
                        $config['options']['paper']['art paper'] = $optId;
                    } elseif (str_contains($val, 'canvas')) {
                        $config['options']['paper']['canvas'] = $optId;
                    }
                }
            }
        } catch (\Throwable) {
            // Fall back to defaults
        }

        $this->resolvedConfig = $config;
        return $this->resolvedConfig;
    }
}
