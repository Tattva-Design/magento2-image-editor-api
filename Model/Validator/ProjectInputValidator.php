<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Validator;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectInputValidator
{
    private const ALLOWED_SIZES = ['a2', 'a3', 'a4', 'a5'];

    /**
     * @param array<string, mixed> $input
     * @return array{name: string, description: ?string, size: string}
     */
    public function validateCreateInput(array $input): array
    {
        $name = $this->requireNonEmptyString('name', $input['name'] ?? null);
        $size = $this->normalizeSize($input['size'] ?? null);
        $description = isset($input['description']) ? trim((string) $input['description']) : null;

        return [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'size' => $size,
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

        if (array_key_exists('canvasObject', $input)) {
            $canvasObject = $input['canvasObject'];
            if ($canvasObject === null) {
                $updateData['canvas_object'] = null;
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

        if ($updateData === []) {
            throw new GraphQlInputException(__('At least one updatable field must be provided.'));
        }

        return $updateData;
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
        $size = strtolower($this->requireNonEmptyString('size', $value));

        if (!in_array($size, self::ALLOWED_SIZES, true)) {
            throw new GraphQlInputException(__('The "size" value must be one of: a2, a3, a4, a5.'));
        }

        return $size;
    }
}
