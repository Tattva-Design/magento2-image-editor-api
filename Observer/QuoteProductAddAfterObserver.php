<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\App\ResourceConnection;
use TattvaDesign\ImageEditorApi\Model\Registry\CartProjectRegistry;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectSuperAttributeMapper;

class QuoteProductAddAfterObserver implements ObserverInterface
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly ProjectSuperAttributeMapper $mapper,
        private readonly CartProjectRegistry $registry
    ) {
    }

    /**
     * Set project_uuid, project name and custom price to the quote item.
     */
    public function execute(Observer $observer): void
    {
        $items = $observer->getEvent()->getItems() ?: [];
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('tattva_image_editor_project');

        foreach ($items as $item) {
            $buyRequest = $item->getOptionByCode('info_buyRequest');
            $data = $buyRequest ? json_decode((string)$buyRequest->getValue(), true) : null;
            $uuid = is_array($data) ? trim((string)($data['project_uuid'] ?? '')) : '';

            $sku = (string)($item->getProduct()?->getSku() ?: $item->getSku());

            if (!$uuid) {
                $uuid = (string)($this->registry->get($sku) ?: '');
            }

            if (!$uuid) {
                continue;
            }

            $item->setData('project_uuid', $uuid);

            if (!$this->mapper->isCustomisable($sku)) {
                continue;
            }

            try {
                $project = $connection->fetchRow(
                    $connection->select()->from($table, ['name', 'size', 'frame_type', 'paper_type'])->where('uuid = ?', $uuid)
                );
                if ($project) {
                    if (!empty($project['name'])) {
                        $item->setName($project['name']);
                    }

                    if ($item->getHasChildren()) {
                        foreach ($item->getChildren() as $child) {
                            $child->setData('project_uuid', $uuid);
                            if (!empty($project['name'])) {
                                $child->setName($project['name']);
                            }
                            $child->setCustomPrice(500.00);
                            $child->setOriginalCustomPrice(500.00);
                            $child->getProduct()?->setIsSuperMode(true);
                        }
                    }

                    $item->setCustomPrice(500.00);
                    $item->setOriginalCustomPrice(500.00);
                    $item->getProduct()?->setIsSuperMode(true);
                }
            } catch (\Throwable) {
                // Keep robust
            }
        }
    }
}
