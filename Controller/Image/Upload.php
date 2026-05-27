<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Controller\Image;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Store\Model\StoreManagerInterface;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerRequestAuthenticator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageUploader;
use TattvaDesign\ImageEditorApi\Model\Validator\ProjectImageInputValidator;

class Upload implements HttpPostActionInterface, CsrfAwareActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly CustomerRequestAuthenticator $customerRequestAuthenticator,
        private readonly ProjectImageInputValidator $projectImageInputValidator,
        private readonly ProjectImageUploader $projectImageUploader
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();

        try {
            $this->applyRequestedStore();

            $customerId = $this->customerRequestAuthenticator->getCustomerId($this->request);
            $projectUuid = trim((string) $this->request->getParam('projectUuid', ''));
            $imageFile = $this->request->getFiles('image');

            if (!is_array($imageFile) || (int) ($imageFile['size'] ?? 0) <= 0) {
                throw new GraphQlInputException(__('The "image" file upload is required.'));
            }

            $binaryContent = @file_get_contents((string) ($imageFile['tmp_name'] ?? ''));
            if ($binaryContent === false || $binaryContent === '') {
                throw new GraphQlInputException(__('The uploaded file could not be read.'));
            }

            $validatedInput = $this->projectImageInputValidator->validateBinaryInput(
                $projectUuid,
                (string) ($imageFile['name'] ?? ''),
                $binaryContent
            );

            $uploadedImage = $this->projectImageUploader->upload($customerId, $validatedInput);

            return $result->setData([
                'success' => true,
                'data' => $uploadedImage,
            ]);
        } catch (GraphQlAuthorizationException|GraphQlInputException|LocalizedException $exception) {
            $result->setHttpResponseCode(400);

            return $result->setData([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            $result->setHttpResponseCode(500);

            return $result->setData([
                'success' => false,
                'message' => 'Unable to upload the image at this time.',
            ]);
        }
    }

    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return true;
    }

    private function applyRequestedStore(): void
    {
        $storeCode = trim((string) $this->request->getHeader('Store'));
        if ($storeCode === '') {
            return;
        }

        $this->storeManager->setCurrentStore($storeCode);
    }
}
