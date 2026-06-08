<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Plugin;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\Serialize\SerializerInterface;

class GraphQlMultipartPlugin
{
    public function __construct(
        private readonly SerializerInterface $jsonSerializer
    ) {
    }

    /**
     * Intercept dispatch to handle multipart/form-data requests for GraphQL.
     *
     * @param \Magento\GraphQl\Controller\GraphQl $subject
     * @param RequestInterface $request
     * @return array|null
     */
    public function beforeDispatch(
        \Magento\GraphQl\Controller\GraphQl $subject,
        RequestInterface $request
    ): ?array {
        if (!$request instanceof HttpRequest || !$request->isPost()) {
            return null;
        }

        $contentType = (string) $request->getHeader('Content-Type');
        if (stripos($contentType, 'multipart/form-data') === false) {
            return null;
        }

        // Check if operations parameter is present in POST parameters
        $operationsJson = $request->getParam('operations');
        if (!is_string($operationsJson) || trim($operationsJson) === '') {
            return null;
        }

        $operations = json_decode($operationsJson, true);
        if (!is_array($operations)) {
            return null;
        }

        // Optionally handle Apollo/urql multipart request spec 'map' field
        $mapJson = $request->getParam('map');
        if (is_string($mapJson) && trim($mapJson) !== '') {
            $map = json_decode($mapJson, true);
            if (is_array($map)) {
                foreach ($map as $fileKey => $variablePaths) {
                    $fileData = $request->getFiles($fileKey);
                    if ($fileData === null || !is_array($fileData) || (int) ($fileData['size'] ?? 0) <= 0) {
                        if (isset($_FILES[$fileKey]) && (int) ($_FILES[$fileKey]['size'] ?? 0) > 0) {
                            $fileData = $_FILES[$fileKey];
                        } else {
                            continue;
                        }
                    }

                    foreach ($variablePaths as $path) {
                        $operations = $this->assignFileToPath($operations, $path, $fileData);
                    }
                }
            }
        }

        // Re-serialize the operations array back into JSON
        try {
            $newRawBody = $this->jsonSerializer->serialize($operations);

            if (method_exists($request, 'setContent')) {
                $request->setContent($newRawBody);
            } else {
                // Use reflection to update the private/protected rawBody / _rawBody / content property of the request
                $reflection = new \ReflectionClass($request);
                $property = null;
                if ($reflection->hasProperty('content')) {
                    $property = $reflection->getProperty('content');
                } elseif ($reflection->hasProperty('_rawBody')) {
                    $property = $reflection->getProperty('_rawBody');
                } elseif ($reflection->hasProperty('rawBody')) {
                    $property = $reflection->getProperty('rawBody');
                }

                if ($property) {
                    $property->setAccessible(true);
                    $property->setValue($request, $newRawBody);
                }
            }
        } catch (\Throwable $e) {
            // Ignore serialization or reflection errors and let Magento handle standard flow
        }

        return null;
    }

    /**
     * Set a value at a dot-separated path in an array.
     *
     * @param array $operations
     * @param string $path
     * @param array $fileData
     * @return array
     */
    private function assignFileToPath(array $operations, string $path, array $fileData): array
    {
        $keys = explode('.', $path);
        $current = &$operations;
        foreach ($keys as $key) {
            if (!is_array($current)) {
                return $operations;
            }
            if (!isset($current[$key]) && !array_key_exists($key, $current)) {
                return $operations;
            }
            $current = &$current[$key];
        }
        $current = $fileData;
        return $operations;
    }
}
