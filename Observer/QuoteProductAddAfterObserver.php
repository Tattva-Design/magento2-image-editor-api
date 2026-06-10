<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class QuoteProductAddAfterObserver implements ObserverInterface
{
    /**
     * Set project_uuid from info_buyRequest options to the quote item database columns.
     */
    public function execute(Observer $observer): void
    {
        $items = $observer->getEvent()->getItems();
        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $buyRequest = $item->getOptionByCode('info_buyRequest');
            if ($buyRequest) {
                $buyRequestData = json_decode($buyRequest->getValue(), true);
                if (is_array($buyRequestData) && isset($buyRequestData['project_uuid'])) {
                    $item->setData('project_uuid', trim((string) $buyRequestData['project_uuid']));
                }
            }
        }
    }
}
