<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use TattvaDesign\ImageEditorApi\Model\Validator\ProjectInputValidator;

class ProjectUpdater
{
    public function __construct(
        private readonly ProjectResource $projectResource,
        private readonly ProjectDataMapper $projectDataMapper,
        private readonly ProjectInputValidator $projectInputValidator
    ) {
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function update(int $customerId, string $uuid, array $input): array
    {
        $projectRow = $this->projectResource->getProjectRowByUuid($customerId, $uuid);
        $updateData = $this->projectInputValidator->validateUpdateInput($input);

        try {
            $this->projectResource->getConnection()->update(
                $this->projectResource->getTableName(),
                $updateData,
                ['id = ?' => (int) $projectRow['id']]
            );
        } catch (LocalizedException $exception) {
            throw new GraphQlInputException($exception->getPhrase());
        } catch (\Throwable $exception) {
            throw new GraphQlInputException(__('Unable to update the project at this time.'));
        }

        return $this->projectDataMapper->mapRow(
            $this->projectResource->getProjectRowById((int) $projectRow['id'])
        );
    }
}
