<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Validator;

use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectImageInputValidator
{
    /**
     * @param array<string, mixed> $input
     * @return array{projectUuid: string, originalName: string, binaryContent: string}
     */
    public function validateCreateInput(array $input): array
    {
        $projectUuid = $input['projectUuid'] ?? null;
        $file = $input['file'] ?? null;

        if (!is_array($file) || !isset($file['tmp_name']) || (int) ($file['size'] ?? 0) <= 0) {
            throw new GraphQlInputException(__('The "file" value must be a valid uploaded file.'));
        }

        $binaryContent = @file_get_contents((string) $file['tmp_name']);
        if ($binaryContent === false || $binaryContent === '') {
            throw new GraphQlInputException(__('The uploaded file could not be read.'));
        }

        return $this->validateBinaryInput(
            $projectUuid,
            $file['name'] ?? '',
            $binaryContent
        );
    }

    /**
     * @return array{projectUuid: string, originalName: string, binaryContent: string}
     */
    public function validateBinaryInput(?string $projectUuid, ?string $originalName, string $binaryContent): array
    {
        $normalizedProjectUuid = $this->requireNonEmptyString('projectUuid', $projectUuid);
        $normalizedOriginalName = $this->requireNonEmptyString('originalName', $originalName);

        if ($binaryContent === '') {
            throw new GraphQlInputException(__('The uploaded file content is empty.'));
        }

        return [
            'projectUuid' => $normalizedProjectUuid,
            'originalName' => $normalizedOriginalName,
            'binaryContent' => $binaryContent,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{originalName: string, binaryContent: string, description: ?string}
     */
    public function validateDefaultInput(array $input): array
    {
        $file = $input['file'] ?? null;
        $description = isset($input['description']) ? trim((string) $input['description']) : null;

        if (!is_array($file) || !isset($file['tmp_name']) || (int) ($file['size'] ?? 0) <= 0) {
            throw new GraphQlInputException(__('The "file" value must be a valid uploaded file.'));
        }

        $binaryContent = @file_get_contents((string) $file['tmp_name']);
        if ($binaryContent === false || $binaryContent === '') {
            throw new GraphQlInputException(__('The uploaded file could not be read.'));
        }

        return [
            'originalName' => trim((string) ($file['name'] ?? '')),
            'binaryContent' => $binaryContent,
            'description' => $description,
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
}
