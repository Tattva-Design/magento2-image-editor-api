<?php

declare(strict_types=1);

namespace TattvaDesign\ImageEditorApi\Model\GraphQl\Type\Scalar;

use Magento\Framework\GraphQl\Schema\Type\Scalar\CustomScalarInterface;
use GraphQL\Language\AST\ValueNode;

class JsonScalar implements CustomScalarInterface
{
    /**
     * Serializes the value for the GraphQL response.
     * Maps the DB stored JSON string (or php array) to a decoded array.
     *
     * @param mixed $value
     * @return mixed
     */
    public function serialize($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }
        return $value;
    }

    /**
     * Parses the value provided by the client (e.g., in variables).
     *
     * @param mixed $value
     * @return mixed
     */
    public function parseValue($value)
    {
        return $value;
    }

    /**
     * Parses the literal value provided in the GraphQL query string.
     *
     * @param ValueNode $valueNode
     * @param array|null $variables
     * @return mixed
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        return \GraphQL\Utils\AST::valueFromASTUntyped($valueNode, $variables);
    }
}
