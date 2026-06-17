<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use TattvaDesign\ImageEditorApi\Model\Util\UuidGenerator;
use TattvaDesign\ImageEditorApi\Model\Constants;

class ProjectImageUploader
{
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
        $temporaryFilePath = Constants::PROJECTS_PATH . $input['projectUuid'] . '/images/' . $temporaryFileName;

        try {
            $imageId = $this->projectImageResource->insertImage([
                'uuid' => $imageUuid,
                'project_id' => $projectId,
                'customer_id' => (int) $projectRow['customer_id'],
                'store_id' => (int) $projectRow['store_id'],
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
        $filePath = Constants::PROJECTS_PATH . $input['projectUuid'] . '/images/' . $fileName;

        $thumbnailFileName = $this->buildThumbnailFileName($imageUuid, $originalName, $imageMetadata['extension']);
        $thumbnailFilePath = Constants::PROJECTS_PATH . $input['projectUuid'] . '/images/' . $thumbnailFileName;

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        try {
            $mediaDirectory->create(dirname($filePath));
            
            // Save original image
            $mediaDirectory->writeFile($filePath, $input['binaryContent']);

            // Generate and save thumbnail
            $thumbnailContent = $this->resizeImage(
                $input['binaryContent'],
                $imageMetadata['mimeType'],
                600
            );
            $mediaDirectory->writeFile($thumbnailFilePath, $thumbnailContent);

            $this->projectImageResource->updateImageStorageData($imageId, $fileName, $filePath, $thumbnailFilePath);
        } catch (\Throwable $exception) {
            if (isset($mediaDirectory)) {
                if ($mediaDirectory->isExist($filePath)) {
                    $mediaDirectory->delete($filePath);
                }
                if ($mediaDirectory->isExist($thumbnailFilePath)) {
                    $mediaDirectory->delete($thumbnailFilePath);
                }
            }
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

    /**
     * Resize image content using PHP GD library to a maximum dimension
     *
     * @param string $binaryContent
     * @param string $mimeType
     * @param int $maxDimension
     * @return string Resized binary content
     */
    private function resizeImage(string $binaryContent, string $mimeType, int $maxDimension = 600): string
    {
        $srcImage = @imagecreatefromstring($binaryContent);
        if (!$srcImage) {
            return $binaryContent; // Fallback to original content if GD fails to load
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);

        if ($width <= $maxDimension && $height <= $maxDimension) {
            imagedestroy($srcImage);
            return $binaryContent; // No resizing needed, return original
        }

        $ratio = min($maxDimension / $width, $maxDimension / $height);
        $newWidth = (int) round($width * $ratio);
        $newHeight = (int) round($height * $ratio);

        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        if (!$dstImage) {
            imagedestroy($srcImage);
            return $binaryContent;
        }

        // Preserve transparency for PNG, WebP and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/webp' || $mimeType === 'image/gif') {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            if ($transparent !== false) {
                imagefilledrectangle($dstImage, 0, 0, $newWidth, $newHeight, $transparent);
            }
        }

        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        ob_start();
        switch ($mimeType) {
            case 'image/jpeg':
                imagejpeg($dstImage, null, 90);
                break;
            case 'image/png':
                imagepng($dstImage, null, 9);
                break;
            case 'image/webp':
                imagewebp($dstImage, null, 90);
                break;
            case 'image/gif':
                imagegif($dstImage);
                break;
            default:
                imagejpeg($dstImage, null, 90);
                break;
        }
        $resizedContent = ob_get_clean();

        imagedestroy($srcImage);
        imagedestroy($dstImage);

        return $resizedContent ?: $binaryContent;
    }

    private function buildThumbnailFileName(string $imageUuid, string $originalName, string $extension): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        return substr($imageUuid, 0, 8) . '-' . $baseName . '-thumbnail.' . strtolower($extension);
    }

    /**
     * @param array{originalName: string, binaryContent: string, description: ?string} $input
     * @return array<string, mixed>
     */
    public function uploadDefault(array $input): array
    {
        $imageMetadata = $this->extractImageMetadata($input['binaryContent'], $input['originalName']);
        $imageUuid = $this->uuidGenerator->generate();

        $originalName = $this->normalizeBaseName($input['originalName']) . '.' . strtolower($imageMetadata['extension']);
        $fileName = substr($imageUuid, 0, 8) . '-' . $originalName;
        
        $defaultsPath = 'tattva/image-editor/defaults/';
        $filePath = $defaultsPath . $fileName;

        try {
            $imageId = $this->projectImageResource->insertImage([
                'uuid' => $imageUuid,
                'project_id' => null,
                'customer_id' => null,
                'store_id' => null,
                'status' => self::IMAGE_STATUS,
                'file_name' => $fileName,
                'original_name' => $originalName,
                'file_path' => $filePath,
                'mime_type' => $imageMetadata['mimeType'],
                'extension' => $imageMetadata['extension'],
                'size_bytes' => $imageMetadata['sizeBytes'],
                'width' => $imageMetadata['width'],
                'height' => $imageMetadata['height'],
                'description' => $input['description'] ?? null,
            ]);
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to upload the default image at this time.'));
        }

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        try {
            $mediaDirectory->create($defaultsPath);
            
            // Save original image
            $mediaDirectory->writeFile($filePath, $input['binaryContent']);
        } catch (\Throwable $exception) {
            if (isset($mediaDirectory) && $mediaDirectory->isExist($filePath)) {
                $mediaDirectory->delete($filePath);
            }
            $this->projectImageResource->deleteImageById($imageId);
            throw new GraphQlInputException(__('Unable to save the default image file.'));
        }

        return $this->projectImageDataMapper->mapRow(
            $this->projectImageResource->getImageRowById($imageId)
        );
    }
}
