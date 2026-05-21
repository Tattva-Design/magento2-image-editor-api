<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Auth;

use Magento\Authorization\Model\UserContextInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Integration\Api\Exception\UserTokenException;
use Magento\Integration\Api\UserTokenReaderInterface;

class CustomerRequestAuthenticator
{
    public function __construct(
        private readonly UserTokenReaderInterface $userTokenReader
    ) {
    }

    public function getCustomerId(RequestInterface $request): int
    {
        $authorizationHeader = trim((string) $request->getHeader('Authorization'));
        if ($authorizationHeader === '') {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        if (!preg_match('/^Bearer\\s+(.+)$/i', $authorizationHeader, $matches)) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        try {
            $userToken = $this->userTokenReader->read(trim($matches[1]));
        } catch (UserTokenException $exception) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        $userContext = $userToken->getUserContext();
        $userId = (int) $userContext->getUserId();
        $userType = (int) $userContext->getUserType();

        if ($userType !== UserContextInterface::USER_TYPE_CUSTOMER || $userId <= 0) {
            throw new GraphQlAuthorizationException(__('The current customer isn\'t authorized.'));
        }

        return $userId;
    }
}
