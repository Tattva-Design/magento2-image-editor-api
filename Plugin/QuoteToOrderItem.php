<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Quote\Model\Quote\Item\ToOrderItem as Subject;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Quote\Model\Quote\Item as QuoteItem;

class QuoteToOrderItem
{
    /**
     * Copy project_uuid from Quote Item to Order Item
     *
     * @param Subject $subject
     * @param OrderItem $orderItem
     * @param QuoteItem $quoteItem
     * @return OrderItem
     */
    public function afterConvert(
        Subject $subject,
        OrderItem $orderItem,
        QuoteItem $quoteItem
    ): OrderItem {
        $projectUuid = $quoteItem->getData('project_uuid');
        if ($projectUuid) {
            $orderItem->setData('project_uuid', $projectUuid);
        }
        return $orderItem;
    }
}
