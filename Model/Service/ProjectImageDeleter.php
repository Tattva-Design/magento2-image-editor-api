<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectImageDeleter
{
    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly ProjectImageResource $projectImageResource,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $customerId, string $imageUuid): array
    {
        $imageRow = $this->projectImageResource->getImageRowByUuid(
            $customerId,
            $this->projectResource->getCurrentStoreId(),
            $imageUuid
        );

        try {
            $this->projectImageResource->deleteImageById((int) $imageRow['id']);

            $filePath = (string) $imageRow['file_path'];
            $thumbnailPath = (string) ($imageRow['thumbnail_path'] ?? '');

            if ($filePath !== '' || $thumbnailPath !== '') {
                $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

                if ($filePath !== '' && $mediaDirectory->isExist($filePath)) {
                    $mediaDirectory->delete($filePath);
                }

                if ($thumbnailPath !== '' && $mediaDirectory->isExist($thumbnailPath)) {
                    $mediaDirectory->delete($thumbnailPath);
                }
            }
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to delete the project image at this time.'));
        }

        return [
            'imageUuid' => $imageUuid,
            'success' => true,
        ];
    }
}
