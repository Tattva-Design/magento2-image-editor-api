<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Framework\App\HttpRequestInterface;
use Magento\GraphQl\Controller\HttpRequestValidator\ContentTypeValidator;

class ContentTypeValidatorPlugin
{
    /**
     * Intercept validation to allow multipart/form-data requests.
     *
     * @param ContentTypeValidator $subject
     * @param callable $proceed
     * @param HttpRequestInterface $request
     * @return void
     */
    public function aroundValidate(
        ContentTypeValidator $subject,
        callable $proceed,
        HttpRequestInterface $request
    ): void {
        $contentType = (string) $request->getHeader('Content-Type');

        // If it is a multipart/form-data request, skip the content type check
        if (stripos($contentType, 'multipart/form-data') !== false) {
            return;
        }

        $proceed($request);
    }
}
