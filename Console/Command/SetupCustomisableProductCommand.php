<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Console\Command;

use Magento\Framework\Console\Cli;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use TattvaDesign\ImageEditorApi\Model\Service\CustomisableProductManager;

class SetupCustomisableProductCommand extends Command
{
    public function __construct(
        private readonly CustomisableProductManager $customisableProductManager,
        ?string $name = null
    ) {
        parent::__construct($name);
    }

    protected function configure(): void
    {
        $this->setName('tattva:image-editor:setup-product')
            ->setDescription('Setup or verify customizable configurable product, 12 simple child variations, and framevala_inventory_source MSI stock assignments')
            ->setAliases(['tattva:setup-customisable-product']);

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Initializing customizable product and variations setup...</info>');

        try {
            $parentId = $this->customisableProductManager->createOrUpdateConfigurableProduct();
            $output->writeln(sprintf('<info>Configurable product verified/updated with ID: %d</info>', $parentId));

            $this->customisableProductManager->ensureStockAndSourceAssigned();
            $output->writeln('<info>Source "framevala_inventory_source" and stocks successfully assigned to parent and all 12 child simple products.</info>');
            $output->writeln('<info>All variations are marked in stock and salable.</info>');
            return Cli::RETURN_SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln(sprintf('<error>Failed to setup customizable product: %s</error>', $e->getMessage()));
            return Cli::RETURN_FAILURE;
        }
    }
}
