<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TattvaDesign\ImageEditorApi\Model\Service\CustomisableProductManager;

class CreateCustomisableProduct implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly CustomisableProductManager $customisableProductManager
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

        $this->customisableProductManager->createOrUpdateConfigurableProduct();

        $this->moduleDataSetup->endSetup();
        return $this;
    }
}
