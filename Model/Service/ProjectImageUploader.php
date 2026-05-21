<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use TattvaDesign\ImageEditorApi\Model\Util\UuidGenerator;

class ProjectImageUploader
{
    private const IMAGE_TYPE = 'original';
    private const IMAGE_STATUS = 'ready';

    /**
     * @var array<string, string>
     */
    private const MIME_EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly ProjectImageResource $projectImageResource,
        private readonly ProjectImageDataMapper $projectImageDataMapper,
        private readonly UuidGenerator $uuidGenerator,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @param array{projectUuid: string, originalName: string, binaryContent: string} $input
     * @return array<string, mixed>
     */
    public function upload(int $customerId, array $input): array
    {
        $projectRow = $this->projectResource->getProjectRowByUuid($customerId, $input['projectUuid']);
        $imageMetadata = $this->extractImageMetadata($input['binaryContent'], $input['originalName']);

        $projectId = (int) $projectRow['id'];
        $imageUuid = $this->uuidGenerator->generate();
        $originalName = $this->buildDisplayOriginalName($projectId, $input['originalName'], $imageMetadata['extension']);
        $temporaryFileName = $imageUuid . '.' . strtolower($imageMetadata['extension']);
        $temporaryFilePath = 'tattva/image-editor/projects/' . $input['projectUuid'] . '/images/' . $temporaryFileName;

        try {
            $imageId = $this->projectImageResource->insertImage([
                'uuid' => $imageUuid,
                'project_id' => $projectId,
                'customer_id' => (int) $projectRow['customer_id'],
                'store_id' => (int) $projectRow['store_id'],
                'type' => self::IMAGE_TYPE,
                'status' => self::IMAGE_STATUS,
                'file_name' => $temporaryFileName,
                'original_name' => $originalName,
                'file_path' => $temporaryFilePath,
                'mime_type' => $imageMetadata['mimeType'],
                'extension' => $imageMetadata['extension'],
                'size_bytes' => $imageMetadata['sizeBytes'],
                'width' => $imageMetadata['width'],
                'height' => $imageMetadata['height'],
            ]);
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to upload the project image at this time.'));
        }

        $fileName = $this->buildStoredFileName($imageUuid, $originalName);
        $filePath = 'tattva/image-editor/projects/' . $input['projectUuid'] . '/images/' . $fileName;
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        try {
            $mediaDirectory->create(dirname($filePath));
            $mediaDirectory->writeFile($filePath, $input['binaryContent']);
            $this->projectImageResource->updateImageStorageData($imageId, $fileName, $filePath);
        } catch (\Throwable $exception) {
            $this->projectImageResource->deleteImageById($imageId);
            throw new GraphQlInputException(__('Unable to upload the project image at this time.'));
        }

        return $this->projectImageDataMapper->mapRow(
            $this->projectImageResource->getImageRowById($imageId)
        );
    }

    /**
     * @return array{mimeType: string, extension: string, sizeBytes: int, width: ?int, height: ?int}
     */
    private function extractImageMetadata(string $binaryContent, string $originalName): array
    {
        $imageInfo = @getimagesizefromstring($binaryContent);
        if ($imageInfo === false) {
            throw new GraphQlInputException(__('The provided file is not a valid image.'));
        }

        $mimeType = (string) ($imageInfo['mime'] ?? '');
        if (!isset(self::MIME_EXTENSION_MAP[$mimeType])) {
            throw new GraphQlInputException(__('Only jpeg, png, webp, and gif images are supported.'));
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = self::MIME_EXTENSION_MAP[$mimeType];
        }

        return [
            'mimeType' => $mimeType,
            'extension' => $extension,
            'sizeBytes' => strlen($binaryContent),
            'width' => isset($imageInfo[0]) ? (int) $imageInfo[0] : null,
            'height' => isset($imageInfo[1]) ? (int) $imageInfo[1] : null,
        ];
    }

    private function buildDisplayOriginalName(int $projectId, string $originalName, string $extension): string
    {
        $normalizedBaseName = $this->normalizeBaseName($originalName);
        $normalizedExtension = strtolower($extension);
        $candidateOriginalName = $normalizedBaseName . '.' . $normalizedExtension;
        $suffix = 1;

        while ($this->projectImageResource->doesProjectOriginalNameExist($projectId, $candidateOriginalName)) {
            $candidateOriginalName = sprintf(
                '%s-%d.%s',
                $normalizedBaseName,
                $suffix,
                $normalizedExtension
            );
            $suffix++;
        }

        return $candidateOriginalName;
    }

    private function buildStoredFileName(string $imageUuid, string $originalName): string
    {
        return substr($imageUuid, 0, 8) . '-' . $originalName;
    }

    private function normalizeBaseName(string $originalName): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $normalizedBaseName = strtolower(trim((string) $baseName));
        $normalizedBaseName = preg_replace('/[^a-z0-9]+/', '-', $normalizedBaseName) ?? '';
        $normalizedBaseName = trim($normalizedBaseName, '-');

        if ($normalizedBaseName === '') {
            return 'image';
        }

        return $normalizedBaseName;
    }
}
