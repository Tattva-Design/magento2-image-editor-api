<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Setup\Patch\Data;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleDirReader;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TattvaDesign\ImageEditorApi\Model\Service\ProjectImageResource;
use TattvaDesign\ImageEditorApi\Model\Util\UuidGenerator;

class AddDefaultImages implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly ProjectImageResource $projectImageResource,
        private readonly UuidGenerator $uuidGenerator,
        private readonly Filesystem $filesystem,
        private readonly ModuleDirReader $moduleDirReader
    ) {
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }

    public function apply(): self
    {
        $this->moduleDataSetup->startSetup();

        $connection = $this->projectImageResource->getConnection();
        $tableName = $this->projectImageResource->getTableName();

        // 1. Determine directories
        $setupDir = $this->moduleDirReader->getModuleDir(Dir::MODULE_SETUP_DIR, 'TattvaDesign_ImageEditorApi');
        $sourceImagesDir = $setupDir . '/Patch/Data/images';

        $mediaDirectory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $targetDefaultsDir = 'tattva/image-editor/defaults';

        // 2. Scan source directory for images
        $sourceFiles = glob($sourceImagesDir . '/*.{png,jpg,jpeg,webp,gif}', GLOB_BRACE);
        if (empty($sourceFiles)) {
            $this->moduleDataSetup->endSetup();
            return $this;
        }

        // Sort files alphabetically to ensure consistent sequence
        sort($sourceFiles);

        // 3. Clear existing default records from DB and media directory
        $connection->delete($tableName, 'project_id IS NULL AND customer_id IS NULL');

        // Ensure media target directory exists and is clean
        try {
            if ($mediaDirectory->isExist($targetDefaultsDir)) {
                $mediaDirectory->delete($targetDefaultsDir);
            }
            $mediaDirectory->create($targetDefaultsDir);
        } catch (\Exception $e) {
            // Ignore directory creation/deletion errors
        }

        // 4. Copy and insert new sequential defaults
        $index = 1;
        foreach ($sourceFiles as $sourcePath) {
            $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
            $formattedIndex = sprintf('%02d', $index);
            
            $targetFileName = $formattedIndex . '.' . $extension;
            $targetPath = $targetDefaultsDir . '/' . $targetFileName;

            // Copy file to target media folder
            $fileContent = @file_get_contents($sourcePath);
            if ($fileContent !== false) {
                $mediaDirectory->writeFile($targetPath, $fileContent);
            }

            // Extract dynamic image metadata
            $imageInfo = @getimagesize($sourcePath);
            $mimeType = $imageInfo !== false ? (string) $imageInfo['mime'] : 'image/png';
            $width = $imageInfo !== false ? (int) $imageInfo[0] : null;
            $height = $imageInfo !== false ? (int) $imageInfo[1] : null;
            $sizeBytes = @filesize($sourcePath) ?: 0;

            // Insert fresh DB record
            $connection->insert($tableName, [
                'uuid' => $this->uuidGenerator->generate(),
                'project_id' => null,
                'customer_id' => null,
                'store_id' => null,
                'status' => 'ready',
                'file_name' => $targetFileName,
                'original_name' => $formattedIndex,
                'file_path' => $targetPath,
                'mime_type' => $mimeType,
                'extension' => $extension,
                'size_bytes' => $sizeBytes,
                'width' => $width,
                'height' => $height,
            ]);

            $index++;
        }

        $this->moduleDataSetup->endSetup();
        return $this;
    }
}
