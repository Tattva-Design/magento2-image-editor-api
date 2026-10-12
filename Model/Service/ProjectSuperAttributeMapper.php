<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

class ProjectSuperAttributeMapper
{
    // Magento configurable super attribute IDs
    public const ATTR_SIZE = 138;
    public const ATTR_FRAME_TYPE = 137;
    public const ATTR_PAPER_TYPE = 139;

    // Option IDs for Size (138)
    public const OPTION_SIZE_A3 = 6;
    public const OPTION_SIZE_A4 = 7;
    public const OPTION_SIZE_A5 = 8;
    public const DEFAULT_SIZE_OPTION_ID = self::OPTION_SIZE_A4;

    // Option IDs for Frame Type (137)
    public const OPTION_FRAME_WITH = 4;
    public const OPTION_FRAME_WITHOUT = 5;
    public const DEFAULT_FRAME_OPTION_ID = self::OPTION_FRAME_WITHOUT;

    // Option IDs for Paper Type (139)
    public const OPTION_PAPER_ART = 9;
    public const OPTION_PAPER_CANVAS = 10;
    public const DEFAULT_PAPER_OPTION_ID = self::OPTION_PAPER_ART;

    /**
     * Determine if a given SKU belongs to a customizable product (handles hyphens, underscores, casing).
     */
    public function isCustomisableProductSku(?string $sku): bool
    {
        if ($sku === null || trim($sku) === '') {
            return false;
        }

        $normalized = strtolower(str_replace(['_', '-'], ' ', trim($sku)));
        return str_starts_with($normalized, 'customisable product')
            || str_starts_with($normalized, 'customise product')
            || str_starts_with($normalized, 'customizable product')
            || str_starts_with($normalized, 'customize product');
    }

    /**
     * Map project size, frame_type, and paper_type to Magento configurable super_attribute array.
     *
     * @param string|null $size
     * @param string|null $frameType
     * @param string|null $paperType
     * @return array<int, int> [attribute_id => option_id]
     */
    public function mapSuperAttributes(?string $size, ?string $frameType, ?string $paperType): array
    {
        return [
            self::ATTR_SIZE => $this->mapSize($size),
            self::ATTR_FRAME_TYPE => $this->mapFrameType($frameType),
            self::ATTR_PAPER_TYPE => $this->mapPaperType($paperType),
        ];
    }

    /**
     * Map project size to option ID.
     */
    public function mapSize(?string $size): int
    {
        $normalized = strtolower(trim((string)$size));
        $normalized = str_replace(['_', '-', ' '], '', $normalized);

        $map = [
            'a3' => self::OPTION_SIZE_A3,
            'a4' => self::OPTION_SIZE_A4,
            'a5' => self::OPTION_SIZE_A5,
        ];

        return $map[$normalized] ?? self::DEFAULT_SIZE_OPTION_ID;
    }

    /**
     * Map project frame type to option ID.
     */
    public function mapFrameType(?string $frameType): int
    {
        $normalized = strtolower(trim((string)$frameType));
        $normalized = str_replace(['_', '-'], ' ', $normalized);

        $map = [
            'with frame' => self::OPTION_FRAME_WITH,
            'withframe' => self::OPTION_FRAME_WITH,
            'frame' => self::OPTION_FRAME_WITH,
            'framed' => self::OPTION_FRAME_WITH,
            'yes' => self::OPTION_FRAME_WITH,
            'without frame' => self::OPTION_FRAME_WITHOUT,
            'withoutframe' => self::OPTION_FRAME_WITHOUT,
            'no frame' => self::OPTION_FRAME_WITHOUT,
            'none' => self::OPTION_FRAME_WITHOUT,
            'no' => self::OPTION_FRAME_WITHOUT,
        ];

        return $map[$normalized] ?? self::DEFAULT_FRAME_OPTION_ID;
    }

    /**
     * Map project paper type to option ID.
     */
    public function mapPaperType(?string $paperType): int
    {
        $normalized = strtolower(trim((string)$paperType));
        $normalized = str_replace(['_', '-'], ' ', $normalized);

        $map = [
            'art paper' => self::OPTION_PAPER_ART,
            'artpaper' => self::OPTION_PAPER_ART,
            'art' => self::OPTION_PAPER_ART,
            'canvas' => self::OPTION_PAPER_CANVAS,
        ];

        return $map[$normalized] ?? self::DEFAULT_PAPER_OPTION_ID;
    }
}
