# Tattva Design Image Editor API

`TattvaDesign_ImageEditorApi` is a minimal Magento 2 module scaffold registered as the Composer package `tattva-design/magento2-image-editor-api`.

## Package Details

- Composer package: `tattva-design/magento2-image-editor-api`
- Magento module: `TattvaDesign_ImageEditorApi`
- Package type: `magento2-module`
- Version: `1.0.0`
- Local package path: `/Users/apple/Workspace/raimptech/magento2-image-editor-api`
- Local Magento install path: `app/code/TattvaDesign/ImageEditorApi`

## Local Installation

From the repository Magento root (`/Users/apple/Workspace/raimptech/framevala-magento/magento`):

```bash
PHPFPM_CONTAINER_ID="$(./bin/docker-compose ps -q phpfpm)"
IMAGE_EDITOR_API_PATH="${IMAGE_EDITOR_API_PATH:-/Users/apple/Workspace/raimptech/magento2-image-editor-api}"

./bin/root mkdir -p /var/www/html/app/code/TattvaDesign/ImageEditorApi
docker cp "${IMAGE_EDITOR_API_PATH}/." "${PHPFPM_CONTAINER_ID}:/var/www/html/app/code/TattvaDesign/ImageEditorApi/"
bin/magento module:enable TattvaDesign_ImageEditorApi
bin/magento setup:upgrade
bin/magento cache:flush
```

## Verification

Use the following commands to confirm the package is present and the module is enabled:

```bash
./bin/root ls -la /var/www/html/app/code/TattvaDesign/ImageEditorApi
bin/magento module:status TattvaDesign_ImageEditorApi
```
