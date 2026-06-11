<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Setup\Patch\Data;

use Magento\Catalog\Model\ProductFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Store\Model\StoreManagerInterface;

class CreateCustomisableProduct implements DataPatchInterface
{
    private const SKU = 'customisable-product';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $state
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        // Prevent 'Area code is not set' exception in CLI
        try {
            $this->state->getAreaCode();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->state->setAreaCode(Area::AREA_ADMINHTML);
        }

        $connection = $this->moduleDataSetup->getConnection();

        try {
            $product = $this->productRepository->get(self::SKU);
            if ($product->getWeight() != 0.5) {
                $product->setWeight(0.5);
                $this->productRepository->save($product);
            }
            $productId = (int)$product->getId();
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Create product
            $product = $this->productFactory->create();
            $product->setTypeId(Type::TYPE_SIMPLE)
                ->setSku(self::SKU)
                ->setName('Customisable Product')
                ->setAttributeSetId($product->getDefaultAttributeSetId())
                ->setPrice(500.00)
                ->setWeight(0.5)
                ->setStatus(Status::STATUS_ENABLED)
                ->setVisibility(Visibility::VISIBILITY_NOT_VISIBLE); // Hidden from catalog search

            // Assign to all websites
            $websiteIds = [];
            foreach ($this->storeManager->getWebsites() as $website) {
                $websiteIds[] = (int)$website->getId();
            }
            $product->setWebsiteIds($websiteIds);

            $product = $this->productRepository->save($product);
            $productId = (int)$product->getId();
        }

        // 1. Populate legacy cataloginventory_stock_item table
        $stockItemTable = $this->moduleDataSetup->getTable('cataloginventory_stock_item');
        if ($connection->isTableExists($stockItemTable)) {
            $connection->insertOnDuplicate($stockItemTable, [
                'product_id' => $productId,
                'stock_id' => 1,
                'qty' => 99999,
                'is_in_stock' => 1,
                'manage_stock' => 0,
                'use_config_manage_stock' => 0
            ]);
        }

        // 2. Populate legacy cataloginventory_stock_status table
        $stockStatusTable = $this->moduleDataSetup->getTable('cataloginventory_stock_status');
        if ($connection->isTableExists($stockStatusTable)) {
            $connection->insertOnDuplicate($stockStatusTable, [
                'product_id' => $productId,
                'website_id' => 0,
                'stock_id' => 1,
                'qty' => 99999,
                'stock_status' => 1
            ]);
        }

        // 3. Populate MSI inventory_source_item table if MSI is enabled
        $inventorySourceItemTable = $this->moduleDataSetup->getTable('inventory_source_item');
        if ($connection->isTableExists($inventorySourceItemTable)) {
            // Insert or update 'default' source assignment
            $connection->insertOnDuplicate($inventorySourceItemTable, [
                'source_code' => 'default',
                'sku' => self::SKU,
                'quantity' => 99999,
                'status' => 1
            ]);
        }

        $this->moduleDataSetup->endSetup();
        return $this;
    }
}
