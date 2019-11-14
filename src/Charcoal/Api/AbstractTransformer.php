<?php

namespace Charcoal\Api;

/**
 * API Transformer
 */
abstract class AbstractTransformer
{
    /**
     * @param array $data Transformer dependencies.
     */
    public function __construct(array $data)
    {
    }

    /**
     * Alias of {@see self::__invoke()}.
     *
     * @param  object $model
     * @return array|null
     */
    public function transform($model)
    {
        if (empty($model['id'])) {
            return null;
        }

        return $this($model);
    }
}
