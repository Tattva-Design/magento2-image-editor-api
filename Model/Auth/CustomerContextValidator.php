<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Auth;

use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\GraphQl\Model\Query\ContextInterface;

class CustomerContextValidator
{
    /**
     * Ensure the GraphQL caller is an authenticated customer and return the customer ID.
     *
     * @param mixed $context
     * @return int
     */
    public function getCustomerId($context): int
    {
        if (!$context instanceof ContextInterface) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $customerId = $context->getUserId();
        $isCustomer = (bool) $context->getExtensionAttributes()->getIsCustomer();

        if (!$isCustomer || $customerId === null || (int) $customerId <= 0) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return (int) $customerId;
    }
}
