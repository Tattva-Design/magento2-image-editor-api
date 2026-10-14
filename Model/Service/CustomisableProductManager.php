<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\App\Area;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;

class CustomisableProductManager
{
    public const SKU = 'customisable-product';
    public const PRODUCT_NAME = 'Customisable Product';
    public const DEFAULT_PRICE = 500.00;
    public const DEFAULT_WEIGHT = 0.5;
    public const INVENTORY_SOURCE_CODE = 'framevala_inventory_source';
    public const SOURCE_FRAMEVALA = 'framevala_inventory_source';

    private const SIZE_OPTIONS = [
        'a3' => ['label' => 'A3', 'fallback_id' => 6, 'sort' => 1],
        'a4' => ['label' => 'A4', 'fallback_id' => 7, 'sort' => 2],
        'a5' => ['label' => 'A5', 'fallback_id' => 8, 'sort' => 3],
    ];

    private const FRAME_OPTIONS = [
        'with frame' => ['label' => 'With Frame', 'fallback_id' => 4, 'sort' => 1],
        'without frame' => ['label' => 'Without Frame', 'fallback_id' => 5, 'sort' => 2],
    ];

    private const PAPER_OPTIONS = [
        'art paper' => ['label' => 'Art Paper', 'fallback_id' => 9, 'sort' => 1],
        'canvas' => ['label' => 'Canvas', 'fallback_id' => 10, 'sort' => 2],
    ];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $state
    ) {
    }

    /**
     * Create or convert the customizable product to a Configurable Product with child simple products.
     *
     * @return int Configurable Product ID
     */
    public function createOrUpdateConfigurableProduct(): int
    {
        // Prevent 'Area code is not set' exception in CLI
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException) {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        }

        $connection = $this->resourceConnection->getConnection();
        $websiteIds = [];
        foreach ($this->storeManager->getWebsites() as $website) {
            $websiteIds[] = (int)$website->getId();
        }

        $tempProduct = $this->productFactory->create();
        $defaultAttributeSetId = (int)$tempProduct->getDefaultAttributeSetId();

        // 1. Resolve configurable attributes (Size, Frame Type, Paper Type)
        $sizeAttr = $this->resolveAttribute('size', ['size'], 138, 'Size', $defaultAttributeSetId);
        $frameAttr = $this->resolveAttribute('frame_type', ['frame_type', 'frame'], 137, 'Frame Type', $defaultAttributeSetId);
        $paperAttr = $this->resolveAttribute('paper_type', ['paper_type', 'paper'], 139, 'Paper Type', $defaultAttributeSetId);

        // 2. Resolve option IDs for each attribute
        $sizeResolvedOptions = [];
        foreach (self::SIZE_OPTIONS as $key => $opt) {
            $sizeResolvedOptions[$key] = [
                'label' => $opt['label'],
                'option_id' => $this->resolveOptionId($sizeAttr['id'], $opt['label'], $opt['fallback_id'], $opt['sort'])
            ];
        }

        $frameResolvedOptions = [];
        foreach (self::FRAME_OPTIONS as $key => $opt) {
            $frameResolvedOptions[$key] = [
                'label' => $opt['label'],
                'option_id' => $this->resolveOptionId($frameAttr['id'], $opt['label'], $opt['fallback_id'], $opt['sort'])
            ];
        }

        $paperResolvedOptions = [];
        foreach (self::PAPER_OPTIONS as $key => $opt) {
            $paperResolvedOptions[$key] = [
                'label' => $opt['label'],
                'option_id' => $this->resolveOptionId($paperAttr['id'], $opt['label'], $opt['fallback_id'], $opt['sort'])
            ];
        }

        // 3. Handle existing product: if it exists and is simple, delete or update type_id to configurable
        $existingProduct = null;
        try {
            $existingProduct = $this->productRepository->get(self::SKU);
        } catch (\Magento\Framework\Exception\NoSuchEntityException) {
            // Product does not exist
        }

        if ($existingProduct && $existingProduct->getTypeId() !== Configurable::TYPE_CODE) {
            try {
                $this->productRepository->delete($existingProduct);
                $existingProduct = null;
            } catch (\Throwable) {
                // If deletion fails (e.g., integrity constraints), update type_id directly
                $connection->update(
                    $this->resourceConnection->getTableName('catalog_product_entity'),
                    ['type_id' => Configurable::TYPE_CODE],
                    ['sku = ?' => self::SKU]
                );
                $existingProduct = $this->productRepository->get(self::SKU);
            }
        }

        // 4. Create / update all 12 child simple variations
        $childProductIds = [];
        $productIntTable = $this->resourceConnection->getTableName('catalog_product_entity_int');

        foreach ($sizeResolvedOptions as $sizeKey => $sizeData) {
            foreach ($frameResolvedOptions as $frameKey => $frameData) {
                foreach ($paperResolvedOptions as $paperKey => $paperData) {
                    $slugParts = [
                        self::SKU,
                        str_replace(' ', '-', $sizeKey),
                        str_replace(' ', '-', $frameKey),
                        str_replace(' ', '-', $paperKey)
                    ];
                    $childSku = strtolower(implode('-', $slugParts));
                    $childName = sprintf(
                        '%s - %s - %s - %s',
                        self::PRODUCT_NAME,
                        $sizeData['label'],
                        $frameData['label'],
                        $paperData['label']
                    );

                    try {
                        $child = $this->productRepository->get($childSku);
                    } catch (\Magento\Framework\Exception\NoSuchEntityException) {
                        $child = $this->productFactory->create();
                        $child->setTypeId(Type::TYPE_SIMPLE)
                            ->setSku($childSku)
                            ->setName($childName)
                            ->setAttributeSetId($defaultAttributeSetId)
                            ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE)
                            ->setWebsiteIds($websiteIds);
                    }

                    $child->setPrice(self::DEFAULT_PRICE)
                        ->setWeight(self::DEFAULT_WEIGHT)
                        ->setStatus(Status::STATUS_ENABLED)
                        ->setStockData([
                            'use_config_manage_stock' => 0,
                            'manage_stock' => 0,
                            'is_in_stock' => 1,
                            'qty' => 99999
                        ]);

                    $child->setData($sizeAttr['code'], $sizeData['option_id']);
                    $child->setData($frameAttr['code'], $frameData['option_id']);
                    $child->setData($paperAttr['code'], $paperData['option_id']);

                    $child = $this->productRepository->save($child);
                    $childId = (int)$child->getId();
                    $childProductIds[] = $childId;

                    // Ensure attribute integer values are populated directly in catalog_product_entity_int
                    if ($connection->isTableExists($productIntTable)) {
                        try {
                            $intCols = array_keys($connection->describeTable($productIntTable));
                            $entityCol = (in_array('row_id', $intCols, true) && !in_array('entity_id', $intCols, true)) ? 'row_id' : 'entity_id';
                            foreach ([
                                $sizeAttr['id'] => $sizeData['option_id'],
                                $frameAttr['id'] => $frameData['option_id'],
                                $paperAttr['id'] => $paperData['option_id']
                            ] as $aId => $oId) {
                                $connection->insertOnDuplicate($productIntTable, [
                                    'attribute_id' => $aId,
                                    'store_id' => 0,
                                    $entityCol => $childId,
                                    'value' => $oId
                                ], ['value']);
                            }
                        } catch (\Throwable) {
                        }
                    }

                    $this->populateStock($childId, $childSku, $websiteIds);
                }
            }
        }

        // 5. Create / update parent Configurable Product
        if (!$existingProduct) {
            $configurableProduct = $this->productFactory->create();
            $configurableProduct->setTypeId(Configurable::TYPE_CODE)
                ->setSku(self::SKU)
                ->setName(self::PRODUCT_NAME)
                ->setAttributeSetId($defaultAttributeSetId)
                ->setVisibility(Visibility::VISIBILITY_BOTH)
                ->setWebsiteIds($websiteIds);
        } else {
            $configurableProduct = $existingProduct;
            $configurableProduct->setTypeId(Configurable::TYPE_CODE)
                ->setName(self::PRODUCT_NAME)
                ->setVisibility(Visibility::VISIBILITY_BOTH);
        }

        $configurableProduct->setPrice(self::DEFAULT_PRICE)
            ->setWeight(self::DEFAULT_WEIGHT)
            ->setStatus(Status::STATUS_ENABLED)
            ->setStockData([
                'use_config_manage_stock' => 0,
                'manage_stock' => 0,
                'is_in_stock' => 1,
                'qty' => 99999
            ]);

        $configurableProduct = $this->productRepository->save($configurableProduct);
        $parentId = (int)$configurableProduct->getId();

        // Ensure type_id is configurable in catalog_product_entity table directly
        try {
            $prodTable = $this->resourceConnection->getTableName('catalog_product_entity');
            $prodCols = array_keys($connection->describeTable($prodTable));
            $pkCol = (in_array('row_id', $prodCols, true) && !in_array('entity_id', $prodCols, true)) ? 'row_id' : 'entity_id';
            $connection->update(
                $prodTable,
                ['type_id' => Configurable::TYPE_CODE],
                [$pkCol . ' = ?' => $parentId]
            );
        } catch (\Throwable) {
        }

        $this->populateStock($parentId, self::SKU, $websiteIds);

        // 6. Populate configurable relationships in database
        $superAttrTable = $this->resourceConnection->getTableName('catalog_product_super_attribute');
        $superAttrLabelTable = $this->resourceConnection->getTableName('catalog_product_super_attribute_label');
        $configurableAttrs = [
            ['id' => $sizeAttr['id'], 'label' => $sizeAttr['label']],
            ['id' => $frameAttr['id'], 'label' => $frameAttr['label']],
            ['id' => $paperAttr['id'], 'label' => $paperAttr['label']]
        ];

        foreach ($configurableAttrs as $position => $attrData) {
            try {
                $connection->insertOnDuplicate($superAttrTable, [
                    'product_id' => $parentId,
                    'attribute_id' => $attrData['id'],
                    'position' => $position
                ], ['position']);

                $psaId = (int)$connection->fetchOne(
                    $connection->select()->from($superAttrTable, ['product_super_attribute_id'])
                        ->where('product_id = ?', $parentId)
                        ->where('attribute_id = ?', $attrData['id'])
                );

                if ($psaId) {
                    $connection->insertOnDuplicate($superAttrLabelTable, [
                        'product_super_attribute_id' => $psaId,
                        'store_id' => 0,
                        'use_default' => 1,
                        'value' => $attrData['label']
                    ], ['value', 'use_default']);
                }
            } catch (\Throwable) {
            }
        }

        // 7. Populate links and relations
        $superLinkTable = $this->resourceConnection->getTableName('catalog_product_super_link');
        $relationTable = $this->resourceConnection->getTableName('catalog_product_relation');

        foreach ($childProductIds as $childId) {
            try {
                $connection->insertOnDuplicate($superLinkTable, [
                    'product_id' => $childId,
                    'parent_id' => $parentId
                ]);

                $connection->insertOnDuplicate($relationTable, [
                    'parent_id' => $parentId,
                    'child_id' => $childId
                ]);
            } catch (\Throwable) {
            }
        }

        // 8. Try to sync via TypeInstance if possible
        try {
            $loadedProduct = $this->productRepository->getById($parentId);
            $loadedProduct->getTypeInstance()->setUsedProductAttributeIds(
                array_column($configurableAttrs, 'id'),
                $loadedProduct
            );
            $loadedProduct->setAssociatedProductIds($childProductIds);
            $this->productRepository->save($loadedProduct);
        } catch (\Throwable) {
            // Database records already established
        }

        return $parentId;
    }

    /**
     * Resolve attribute ID and code from EAV tables, creating if missing.
     *
     * @return array{id: int, code: string, label: string}
     */
    private function resolveAttribute(
        string $expectedCode,
        array $aliases,
        int $fallbackId,
        string $defaultLabel,
        int $attributeSetId
    ): array {
        $connection = $this->resourceConnection->getConnection();
        $eavAttrTable = $this->resourceConnection->getTableName('eav_attribute');
        $catalogEavTable = $this->resourceConnection->getTableName('catalog_eav_attribute');
        $eavEntityAttrTable = $this->resourceConnection->getTableName('eav_entity_attribute');

        $attrCond = $connection->quoteInto('attribute_id = ?', $fallbackId)
            . ' OR '
            . $connection->quoteInto('attribute_code IN (?)', $aliases);

        $select = $connection->select()
            ->from($eavAttrTable, ['attribute_id', 'attribute_code', 'frontend_label'])
            ->where('entity_type_id = ?', 4)
            ->where($attrCond)
            ->limit(1);

        $row = $connection->fetchRow($select);
        if ($row) {
            $attrId = (int)$row['attribute_id'];
            $attrCode = (string)$row['attribute_code'];
            $label = !empty($row['frontend_label']) ? (string)$row['frontend_label'] : $defaultLabel;
        } else {
            // Insert missing EAV attribute
            $connection->insert($eavAttrTable, [
                'entity_type_id' => 4,
                'attribute_code' => $expectedCode,
                'backend_type' => 'int',
                'frontend_input' => 'select',
                'frontend_label' => $defaultLabel,
                'source_model' => 'Magento\\Eav\\Model\\Entity\\Attribute\\Source\\Table',
                'is_required' => 0,
                'is_user_defined' => 1,
                'is_unique' => 0
            ]);
            $attrId = (int)$connection->lastInsertId($eavAttrTable);
            $attrCode = $expectedCode;
            $label = $defaultLabel;
        }

        // Ensure catalog_eav_attribute settings (global scope, visible, product listing)
        if ($connection->isTableExists($catalogEavTable)) {
            try {
                $tableCols = array_keys($connection->describeTable($catalogEavTable));
                $catalogData = [
                    'attribute_id' => $attrId,
                    'is_global' => 1,
                    'is_visible' => 1,
                    'used_in_product_listing' => 1
                ];
                $filteredData = array_intersect_key($catalogData, array_flip($tableCols));
                $updateCols = array_values(array_diff(array_keys($filteredData), ['attribute_id']));
                if (!empty($updateCols)) {
                    $connection->insertOnDuplicate($catalogEavTable, $filteredData, $updateCols);
                }
            } catch (\Throwable) {
            }
        }

        // Ensure assigned to the default attribute set
        if ($connection->isTableExists($eavEntityAttrTable)) {
            try {
                $groupTable = $this->resourceConnection->getTableName('eav_attribute_group');
                $groupId = (int)$connection->fetchOne(
                    $connection->select()->from($groupTable, ['attribute_group_id'])
                        ->where('attribute_set_id = ?', $attributeSetId)
                        ->order('sort_order ASC')
                        ->limit(1)
                );

                if ($groupId) {
                    $connection->insertOnDuplicate($eavEntityAttrTable, [
                        'entity_type_id' => 4,
                        'attribute_set_id' => $attributeSetId,
                        'attribute_group_id' => $groupId,
                        'attribute_id' => $attrId,
                        'sort_order' => 100
                    ]);
                }
            } catch (\Throwable) {
            }
        }

        return ['id' => $attrId, 'code' => $attrCode, 'label' => $label];
    }

    /**
     * Resolve option ID for an attribute option label.
     */
    private function resolveOptionId(int $attrId, string $label, int $fallbackOptionId, int $sortOrder): int
    {
        $connection = $this->resourceConnection->getConnection();
        $optionTable = $this->resourceConnection->getTableName('eav_attribute_option');
        $optionValTable = $this->resourceConnection->getTableName('eav_attribute_option_value');

        $optCond = $connection->quoteInto('LOWER(TRIM(v.value)) = ?', strtolower(trim($label)))
            . ' OR '
            . $connection->quoteInto('o.option_id = ?', $fallbackOptionId);

        // Look for existing option matching label or fallback ID
        $select = $connection->select()
            ->from(['o' => $optionTable], ['o.option_id'])
            ->joinLeft(['v' => $optionValTable], 'o.option_id = v.option_id', [])
            ->where('o.attribute_id = ?', $attrId)
            ->where($optCond)
            ->limit(1);

        $optionId = $connection->fetchOne($select);
        if ($optionId) {
            return (int)$optionId;
        }

        // Create option
        try {
            $connection->insert($optionTable, [
                'attribute_id' => $attrId,
                'sort_order' => $sortOrder
            ]);
            $newOptionId = (int)$connection->lastInsertId($optionTable);

            $connection->insertOnDuplicate($optionValTable, [
                'option_id' => $newOptionId,
                'store_id' => 0,
                'value' => $label
            ]);

            return $newOptionId;
        } catch (\Throwable) {
            return $fallbackOptionId;
        }
    }

    /**
     * Get all 12 child simple SKUs.
     *
     * @return string[]
     */
    public function getAllChildSkus(): array
    {
        $skus = [];
        foreach (array_keys(self::SIZE_OPTIONS) as $sizeKey) {
            foreach (array_keys(self::FRAME_OPTIONS) as $frameKey) {
                foreach (array_keys(self::PAPER_OPTIONS) as $paperKey) {
                    $slugParts = [
                        self::SKU,
                        str_replace(' ', '-', $sizeKey),
                        str_replace(' ', '-', $frameKey),
                        str_replace(' ', '-', $paperKey)
                    ];
                    $skus[] = strtolower(implode('-', $slugParts));
                }
            }
        }
        return $skus;
    }

    /**
     * Get all 13 SKUs (parent configurable SKU + 12 child simple SKUs).
     *
     * @return string[]
     */
    public function getAllSkus(): array
    {
        return array_merge([self::SKU], $this->getAllChildSkus());
    }

    /**
     * Ensure customizable configurable product, 12 child products, and MSI/legacy stock assignments exist.
     */
    public function ensureStockAndSourceAssigned(): void
    {
        $connection = $this->resourceConnection->getConnection();
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $superLinkTable = $this->resourceConnection->getTableName('catalog_product_super_link');

        $parentId = (int)$connection->fetchOne(
            $connection->select()->from($productTable, ['entity_id'])->where('sku = ?', self::SKU)
        );

        $childCount = 0;
        if ($connection->isTableExists($superLinkTable) && $parentId) {
            $childCount = (int)$connection->fetchOne(
                $connection->select()->from($superLinkTable, ['count(*)'])->where('parent_id = ?', $parentId)
            );
        }

        // If parent or children do not exist properly, recreate/update them
        if (!$parentId || $childCount < 12) {
            $parentId = $this->createOrUpdateConfigurableProduct();
        }

        // Collect all websites
        $websiteIds = [];
        try {
            foreach ($this->storeManager->getWebsites() as $website) {
                $websiteIds[] = (int)$website->getId();
            }
        } catch (\Throwable) {
            // CLI fallback
        }

        $websiteTable = $this->resourceConnection->getTableName('store_website');
        if ($connection->isTableExists($websiteTable)) {
            try {
                $fetchedWebs = $connection->fetchCol(
                    $connection->select()->from($websiteTable, ['website_id'])->where('website_id > 0')
                );
                if (!empty($fetchedWebs)) {
                    $websiteIds = array_unique(array_merge($websiteIds, array_map('intval', $fetchedWebs)));
                }
            } catch (\Throwable) {
            }
        }

        // Fetch all 13 SKUs and their entity IDs
        $allSkus = $this->getAllSkus();
        $skuToId = $connection->fetchPairs(
            $connection->select()->from($productTable, ['sku', 'entity_id'])->where('sku IN (?)', $allSkus)
        );

        foreach ($allSkus as $sku) {
            $prodId = isset($skuToId[$sku]) ? (int)$skuToId[$sku] : 0;
            if ($prodId > 0) {
                $this->populateStock($prodId, $sku, $websiteIds);
            }
        }
    }

    /**
     * Populate stock tables for a product ID and SKU across MSI and legacy tables.
     */
    private function populateStock(int $productId, string $sku, array $websiteIds): void
    {
        $connection = $this->resourceConnection->getConnection();

        // 1. Ensure inventory source framevala_inventory_source exists and is enabled
        $sources = [self::SOURCE_FRAMEVALA];
        $inventorySourceTable = $this->resourceConnection->getTableName('inventory_source');
        if ($connection->isTableExists($inventorySourceTable)) {
            $sourceExists = $connection->fetchOne(
                $connection->select()->from($inventorySourceTable, ['source_code'])
                    ->where('source_code = ?', self::SOURCE_FRAMEVALA)
            );
            if (!$sourceExists) {
                try {
                    $connection->insert($inventorySourceTable, [
                        'source_code' => self::SOURCE_FRAMEVALA,
                        'name' => 'Framevala Inventory Source',
                        'enabled' => 1,
                        'country_id' => 'IN',
                        'postcode' => '000000',
                        'city' => 'Surat',
                        'street' => 'Default Street'
                    ]);
                } catch (\Throwable) {
                }
            }
        }

        // 2. Discover all stocks
        $inventoryStockTable = $this->resourceConnection->getTableName('inventory_stock');
        $stockIds = [1];
        if ($connection->isTableExists($inventoryStockTable)) {
            try {
                $select = $connection->select()->from($inventoryStockTable, ['stock_id']);
                $fetchedStocks = $connection->fetchCol($select);
                if (!empty($fetchedStocks)) {
                    $stockIds = array_unique(array_merge($stockIds, array_map('intval', $fetchedStocks)));
                }
            } catch (\Throwable) {
            }
        }

        // 3. Link framevala_inventory_source to all stocks in inventory_source_stock_link
        $sourceStockLinkTable = $this->resourceConnection->getTableName('inventory_source_stock_link');
        if ($connection->isTableExists($sourceStockLinkTable)) {
            foreach ($stockIds as $sId) {
                try {
                    $connection->insertOnDuplicate($sourceStockLinkTable, [
                        'stock_id' => (int)$sId,
                        'source_code' => self::SOURCE_FRAMEVALA,
                        'priority' => 1
                    ], ['priority']);
                } catch (\Throwable) {
                }
            }
        }

        // 4. Ensure inventory_stock_sales_channel maps active websites to primary stock
        $salesChannelTable = $this->resourceConnection->getTableName('inventory_stock_sales_channel');
        $websiteTable = $this->resourceConnection->getTableName('store_website');
        if ($connection->isTableExists($salesChannelTable) && $connection->isTableExists($websiteTable)) {
            try {
                $websites = $connection->fetchAll(
                    $connection->select()->from($websiteTable, ['website_id', 'code'])
                );
                $primaryStockId = $stockIds[0] ?? 1;
                foreach ($websites as $web) {
                    $webCode = (string)$web['code'];
                    if ($webCode === 'admin') {
                        continue;
                    }
                    $existing = $connection->fetchOne(
                        $connection->select()->from($salesChannelTable, ['stock_id'])
                            ->where('type = ?', 'website')
                            ->where('code = ?', $webCode)
                    );
                    if (!$existing) {
                        $connection->insert($salesChannelTable, [
                            'stock_id' => $primaryStockId,
                            'type' => 'website',
                            'code' => $webCode
                        ]);
                    }
                }
            } catch (\Throwable) {
            }
        }

        // 5. Populate MSI inventory_source_item table - assign ONLY framevala_inventory_source
        $inventorySourceItemTable = $this->resourceConnection->getTableName('inventory_source_item');
        if ($connection->isTableExists($inventorySourceItemTable)) {
            try {
                // Remove other source assignments for this SKU so ONLY framevala_inventory_source is assigned
                $connection->delete($inventorySourceItemTable, [
                    'sku = ?' => $sku,
                    'source_code != ?' => self::SOURCE_FRAMEVALA
                ]);
            } catch (\Throwable) {
            }

            try {
                $connection->insertOnDuplicate($inventorySourceItemTable, [
                    'source_code' => self::SOURCE_FRAMEVALA,
                    'sku' => $sku,
                    'quantity' => 99999,
                    'status' => 1
                ], ['quantity', 'status']);
            } catch (\Throwable) {
            }
        }

        // 6. Populate MSI stock index tables (inventory_stock_{stock_id})
        foreach ($stockIds as $sId) {
            $stockIndexTable = $this->resourceConnection->getTableName('inventory_stock_' . $sId);
            if ($connection->isTableExists($stockIndexTable)) {
                try {
                    $connection->insertOnDuplicate($stockIndexTable, [
                        'sku' => $sku,
                        'quantity' => 99999,
                        'is_salable' => 1
                    ], ['quantity', 'is_salable']);
                } catch (\Throwable) {
                }
            }
        }

        // 7. Populate legacy cataloginventory_stock_item table for all stocks
        $stockItemTable = $this->resourceConnection->getTableName('cataloginventory_stock_item');
        if ($connection->isTableExists($stockItemTable)) {
            foreach ($stockIds as $sId) {
                try {
                    $connection->insertOnDuplicate($stockItemTable, [
                        'product_id' => $productId,
                        'stock_id' => (int)$sId,
                        'qty' => 99999,
                        'is_in_stock' => 1,
                        'manage_stock' => 0,
                        'use_config_manage_stock' => 0
                    ], ['qty', 'is_in_stock', 'manage_stock', 'use_config_manage_stock']);
                } catch (\Throwable) {
                }
            }
        }

        // 8. Populate legacy cataloginventory_stock_status table for admin (0) and all websites
        $stockStatusTable = $this->resourceConnection->getTableName('cataloginventory_stock_status');
        if ($connection->isTableExists($stockStatusTable)) {
            $allWebsites = array_unique(array_merge([0], $websiteIds));
            foreach ($stockIds as $sId) {
                foreach ($allWebsites as $webId) {
                    try {
                        $connection->insertOnDuplicate($stockStatusTable, [
                            'product_id' => $productId,
                            'website_id' => (int)$webId,
                            'stock_id' => (int)$sId,
                            'qty' => 99999,
                            'stock_status' => 1
                        ], ['qty', 'stock_status']);
                    } catch (\Throwable) {
                    }
                }
            }
        }

        // 9. Assign product to all websites in catalog_product_website
        $productWebsiteTable = $this->resourceConnection->getTableName('catalog_product_website');
        if ($connection->isTableExists($productWebsiteTable)) {
            foreach ($websiteIds as $webId) {
                if ($webId > 0) {
                    try {
                        $connection->insertOnDuplicate($productWebsiteTable, [
                            'product_id' => $productId,
                            'website_id' => (int)$webId
                        ]);
                    } catch (\Throwable) {
                    }
                }
            }
        }

        // 10. Attempt official Magento MSI SourceItemsSaveInterface if available
        try {
            if (interface_exists('Magento\\InventoryApi\\Api\\SourceItemsSaveInterface')) {
                $sourceItemsSave = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    'Magento\\InventoryApi\\Api\\SourceItemsSaveInterface'
                );
                $sourceItemFactory = \Magento\Framework\App\ObjectManager::getInstance()->get(
                    'Magento\\InventoryApi\\Api\\Data\\SourceItemInterfaceFactory'
                );
                $itemsToSave = [];
                foreach ($sources as $sourceCode) {
                    $sourceItem = $sourceItemFactory->create();
                    $sourceItem->setSourceCode($sourceCode);
                    $sourceItem->setSku($sku);
                    $sourceItem->setQuantity(99999);
                    $sourceItem->setStatus(1);
                    $itemsToSave[] = $sourceItem;
                }
                $sourceItemsSave->execute($itemsToSave);
            }
        } catch (\Throwable) {
        }
    }
}
