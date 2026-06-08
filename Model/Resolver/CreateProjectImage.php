<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Resolver;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use TattvaDesign\ImageEditorApi\Model\Auth\CustomerContextValidator;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageUploader;
use TattvaDesign\ImageEditorApi\Model\Validator\ProjectImageInputValidator;

class CreateProjectImage implements ResolverInterface
{
    public function __construct(
        private readonly CustomerContextValidator $customerContextValidator,
        private readonly ProjectImageInputValidator $projectImageInputValidator,
        private readonly ProjectImageUploader $projectImageUploader,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @inheritdoc
     */
    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ): array {
        $customerId = $this->customerContextValidator->getCustomerId($context);
        $inputArgs = $args['input'] ?? [];

        // Check for uploaded file in the request
        $imageFile = $this->request->getFiles('image');
        if (!$this->isValidUploadedFile($imageFile)) {
            // Check if there is any other uploaded file (e.g. from standard multipart or custom field names)
            $allFiles = $this->request->getFiles();
            if (is_array($allFiles) || $allFiles instanceof \Countable) {
                foreach ($allFiles as $file) {
                    if ($this->isValidUploadedFile($file)) {
                        $imageFile = $file;
                        break;
                    }
                }
            }
        }
        if (!$this->isValidUploadedFile($imageFile)) {
            throw new GraphQlInputException(__('An image file upload is required.'));
        }

        $binaryContent = @file_get_contents((string) ($imageFile['tmp_name'] ?? ''));
        if ($binaryContent === false || $binaryContent === '') {
            throw new GraphQlInputException(__('The uploaded file could not be read.'));
        }

        $projectUuid = $inputArgs['projectUuid'] ?? '';
        $originalName = $imageFile['name'] ?? '';

        $validatedInput = $this->projectImageInputValidator->validateBinaryInput(
            $projectUuid,
            $originalName,
            $binaryContent
        );

        return $this->projectImageUploader->upload($customerId, $validatedInput);
    }

    /**
     * Check if the uploaded file array is valid.
     *
     * @param mixed $file
     * @return bool
     */
    private function isValidUploadedFile(mixed $file): bool
    {
        return is_array($file) && isset($file['tmp_name']) && (int) ($file['size'] ?? 0) > 0;
    }
}
