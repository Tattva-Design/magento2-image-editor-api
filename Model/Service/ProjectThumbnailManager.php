<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectThumbnailManager
{
    private const MIME_EXTENSION_MAP = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * Save a base64 encoded thumbnail image to media storage.
     *
     * @param string $projectUuid
     * @param string $base64String
     * @return string Relative file path
     * @throws GraphQlInputException
     */
    public function saveThumbnailFromBase64(string $projectUuid, string $base64String): string
    {
        $base64String = trim($base64String);
        if ($base64String === '') {
            throw new GraphQlInputException(__('Thumbnail content cannot be empty.'));
        }

        // Handle Data URL format: "data:image/png;base64,..."
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/i', $base64String, $matches)) {
            $extension = strtolower($matches[1]);
            $base64Data = $matches[2];
        } else {
            // Treat as raw base64 string
            $base64Data = $base64String;
            $extension = 'png'; // default fallback extension
        }

        $binaryContent = base64_decode($base64Data, true);
        if ($binaryContent === false) {
            throw new GraphQlInputException(__('Invalid base64 encoding for thumbnail.'));
        }

        $imageInfo = @getimagesizefromstring($binaryContent);
        if ($imageInfo === false) {
            throw new GraphQlInputException(__('The provided thumbnail is not a valid image.'));
        }

        $mimeType = (string) ($imageInfo['mime'] ?? '');
        if (!isset(self::MIME_EXTENSION_MAP[$mimeType])) {
            throw new GraphQlInputException(__('Only jpeg, png, webp, and gif thumbnails are supported.'));
        }

        // Override extension to match actual mime type if mapped
        $extension = self::MIME_EXTENSION_MAP[$mimeType];

        $fileName = 'thumbnail.' . $extension;
        $filePath = 'tattva/image-editor/projects/' . $projectUuid . '/' . $fileName;
        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        try {
            $mediaDirectory->create(dirname($filePath));
            $mediaDirectory->writeFile($filePath, $binaryContent);
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to save the project thumbnail at this time.'));
        }

        $chars = 'abcdefghijklmnopqrstuvwxyz';
        $randomQuery = '';
        for ($i = 0; $i < 4; $i++) {
            $randomQuery .= $chars[random_int(0, 25)];
        }

        return $filePath . '?' . $randomQuery;
    }
}
