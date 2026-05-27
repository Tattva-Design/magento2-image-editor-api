<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\Service;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;

class ProjectDeleter
{
    public function __construct(private readonly ProjectResource $projectResource)
    {
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
