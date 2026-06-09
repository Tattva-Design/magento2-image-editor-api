<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use TattvaDesign\ImageEditorApi\Model\Constants;

class ProjectDeleter
{
    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly Filesystem $filesystem
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function delete(int $customerId, string $uuid): array
    {
        $projectRow = $this->projectResource->getProjectRowByUuid($customerId, $uuid);

        try {
            $this->projectResource->getConnection()->delete(
                $this->projectResource->getTableName(),
                ['id = ?' => (int) $projectRow['id']]
            );

            $projectDirectoryPath = Constants::PROJECTS_PATH . $uuid;
            $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
            if ($mediaDirectory->isExist($projectDirectoryPath)) {
                $mediaDirectory->delete($projectDirectoryPath);
            }
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to delete the project at this time.'));
        }

        return [
            'uuid' => $uuid,
            'success' => true,
        ];
    }
}
