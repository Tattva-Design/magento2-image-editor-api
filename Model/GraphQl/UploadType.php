<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\GraphQl;

use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;

class UploadType implements CustomScalarInterface
{
    /**
     * @inheritdoc
     */
    public function serialize($value)
    {
        return $value;
    }

    /**
     * @inheritdoc
     */
    public function parseValue($value)
    {
        return $value;
    }

    /**
     * @inheritdoc
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return $valueNode->value;
    }
}
