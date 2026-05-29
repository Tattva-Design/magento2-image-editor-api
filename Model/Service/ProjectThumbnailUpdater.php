<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use TattvaDesign\ImageEditorApi\Model\Validator\ProjectInputValidator;

class ProjectThumbnailUpdater
{
    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly ProjectDataMapper $projectDataMapper,
        private readonly ProjectInputValidator $projectInputValidator,
        private readonly ProjectThumbnailManager $projectThumbnailManager
    ) {
    }

    /**
     * Update the canvas thumbnail for the project.
     *
     * @param int $customerId
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     * @throws GraphQlInputException
     */
    public function updateThumbnail(int $customerId, array $input): array
    {
        $validatedInput = $this->projectInputValidator->validateUpdateThumbnailInput($input);
        $projectRow = $this->projectResource->getProjectRowByUuid($customerId, $validatedInput['projectUuid']);

        $thumbnailPath = $this->projectThumbnailManager->saveThumbnailFromBase64(
            $validatedInput['projectUuid'],
            $validatedInput['thumbnail']
        );

        try {
            $this->projectResource->getConnection()->update(
                $this->projectResource->getTableName(),
                ['thumbnail' => $thumbnailPath],
                ['id = ?' => (int) $projectRow['id']]
            );
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to update the project thumbnail at this time.'));
        }

        return $this->projectDataMapper->mapRow(
            $this->projectResource->getProjectRowById((int) $projectRow['id'])
        );
    }
}
