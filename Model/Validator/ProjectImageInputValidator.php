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
        $projectUuid = $this->requireNonEmptyString('projectUuid', $input['projectUuid'] ?? null);
        $originalName = $this->requireNonEmptyString('originalName', $input['originalName'] ?? null);
        $base64Content = trim((string) ($input['base64Content'] ?? ''));

        if ($base64Content === '') {
            throw new GraphQlInputException(__('The "base64Content" value must be a non-empty string.'));
        }

        if (str_contains($base64Content, ',')) {
            $parts = explode(',', $base64Content, 2);
            $base64Content = $parts[1];
        }

        $binaryContent = base64_decode($base64Content, true);
        if ($binaryContent === false || $binaryContent === '') {
            throw new GraphQlInputException(__('The "base64Content" value must be valid base64 image data.'));
        }

        return [
            'projectUuid' => $projectUuid,
            'originalName' => $originalName,
            'binaryContent' => $binaryContent,
        ];
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
