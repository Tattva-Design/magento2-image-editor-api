<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Validator;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectInputValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{name: string, description: ?string, size: string, width: int, height: int}
     */
    public function validateCreateInput(array $input): array
    {
        $name = $this->requireNonEmptyString('name', $input['name']);
        $size = $this->normalizeSize($input['size']);
        $description = isset($input['description']) ? trim((string) $input['description']) : null;
        $width = $this->validateDimension('width', $input['width']);
        $height = $this->validateDimension('height', $input['height']);
        $productSku = isset($input['productSku']) ? trim((string) $input['productSku']) : null;
        $frameType = isset($input['frameType']) ? trim((string) $input['frameType']) : null;
        $paperType = isset($input['paperType']) ? trim((string) $input['paperType']) : null;

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'size' => $size,
            'width' => $width,
            'height' => $height,
            'product_sku' => $productSku !== '' ? $productSku : null,
            'frame_type' => $frameType !== '' ? $frameType : null,
            'paper_type' => $paperType !== '' ? $paperType : null,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function validateUpdateInput(array $input): array
    {
        $updateData = [];

        if (array_key_exists('name', $input)) {
            $updateData['name'] = $this->requireNonEmptyString('name', $input['name']);
        }

        if (array_key_exists('description', $input)) {
            $description = $input['description'];
            if ($description === null) {
                $updateData['description'] = null;
            } else {
                $updateData['description'] = trim((string) $description);
            }
        }

        if (array_key_exists('size', $input)) {
            $updateData['size'] = $this->normalizeSize($input['size']);
        }

        if (array_key_exists('width', $input)) {
            $updateData['width'] = $this->validateDimension('width', $input['width']);
        }

        if (array_key_exists('height', $input)) {
            $updateData['height'] = $this->validateDimension('height', $input['height']);
        }

        if (array_key_exists('canvasObject', $input)) {
            $canvasObject = $input['canvasObject'];
            if ($canvasObject === null) {
                $updateData['canvas_object'] = null;
            } else {
                if (is_array($canvasObject) || is_object($canvasObject)) {
                    $updateData['canvas_object'] = json_encode($canvasObject);
                } else {
                    $canvasObjectString = trim((string) $canvasObject);
                    if ($canvasObjectString === '') {
                        $updateData['canvas_object'] = null;
                    } else {
                        json_decode($canvasObjectString, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            throw new GraphQlInputException(__('The "canvasObject" value must be valid JSON.'));
                        }
                        $updateData['canvas_object'] = $canvasObjectString;
                    }
                }
            }
        }

        if (array_key_exists('status', $input)) {
            $updateData['status'] = $this->requireNonEmptyString('status', $input['status']);
        }

        if (array_key_exists('productSku', $input)) {
            $productSku = $input['productSku'];
            if ($productSku === null) {
                $updateData['product_sku'] = null;
            } else {
                $updateData['product_sku'] = trim((string) $productSku);
            }
        }

        if (array_key_exists('frameType', $input)) {
            $frameType = $input['frameType'];
            if ($frameType === null) {
                $updateData['frame_type'] = null;
            } else {
                $updateData['frame_type'] = trim((string) $frameType);
            }
        }

        if (array_key_exists('paperType', $input)) {
            $paperType = $input['paperType'];
            if ($paperType === null) {
                $updateData['paper_type'] = null;
            } else {
                $updateData['paper_type'] = trim((string) $paperType);
            }
        }

        if ($updateData === []) {
            throw new GraphQlInputException(__('At least one updatable field must be provided.'));
        }

        return $updateData;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{projectUuid: string, thumbnail: ?string}
     */
    public function validateUpdateThumbnailInput(array $input): array
    {
        $projectUuid = $this->requireNonEmptyString('projectUuid', $input['projectUuid'] ?? '');
        $thumbnail = isset($input['thumbnail']) ? trim((string) $input['thumbnail']) : null;

        return [
            'projectUuid' => $projectUuid,
            'thumbnail' => $thumbnail !== '' ? $thumbnail : null,
        ];
    }

    private function requireNonEmptyString(string $fieldName, mixed $value): string
    {
        $normalizedValue = trim((string) $value);

        if ($normalizedValue === '') {
            throw new GraphQlInputException(
                __('The "%1" value must be a non-empty string.', $fieldName)
            );
        }

        return $normalizedValue;
    }

    private function normalizeSize(mixed $value): string
    {
        return $this->requireNonEmptyString('size', $value);
    }

    private function validateDimension(string $fieldName, mixed $value): int
    {
        if ($value === null || trim((string)$value) === '') {
            throw new GraphQlInputException(
                __('The "%1" value must be a positive integer.', $fieldName)
            );
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new GraphQlInputException(
                __('The "%1" value must be a positive integer.', $fieldName)
            );
        }

        return $intValue;
    }
}
