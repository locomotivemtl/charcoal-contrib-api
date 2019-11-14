<?php

namespace Charcoal\Api\Service;

/**
 * The Charcoal API Parameter Validator Service.
 */
class ParameterValidator
{
    /**
     * @const bool The default strict mode flag.
     */
    const DEFAULT_STRICT_MODE = false;

    /**
     * @var array
     */
    private $rules;

    /**
     * @var bool
     */
    private $isStrictMode;

    /**
     * @param array   $rules The parameters rules.
     * @param bool $flag  The strict flag. Strict mode will check for superfluous parameters.
     */
    public function __construct(array $rules, $flag = self::DEFAULT_STRICT_MODE)
    {
        $this->rules        = $this->parseRules($rules);
        $this->isStrictMode = (bool)$flag;
    }

    /**
     * Run validation against given parameters.
     *
     * Returns true if query parameters are valid. Returns an array of errors otherwise.
     *
     * @param  array $parameters The parameters to validate against the rules, typically from query or request body.
     * @return array|bool
     */
    public function validate(array $parameters)
    {
        $errors = [];
        foreach ($this->rules as $name => $options) {
            if ($options['required'] === true && !isset($parameters[$name])) {
                $errors[] = [
                    'message' => sprintf('Parameter "%s" is required.', $name),
                ];
                continue;
            }
            if (isset($parameters[$name])) {
                if ($options['empty'] === true && empty($parameters[$name]) === false) {
                    $errors[] = [
                        'message' => sprintf('Parameter "%s" can only be empty.', $name),
                    ];
                }
                if ($options['empty'] === false && empty($parameters[$name]) === true) {
                    $errors[] = [
                        'message' => sprintf('Parameter "%s" can not be empty.', $name),
                    ];
                }
                if ($options['callback'] !== null) {
                    $callbackResult = call_user_func($options['callback'], $parameters[$name]);
                    if ($callbackResult !== true) {
                        $errors[] = [
                            'message' => is_string($options['callbackMessage']) ? $options['callbackMessage'] : sprintf('Parameter "%s" failed callback validation.', $name),
                        ];
                    }
                }
                if ($options['type'] === 'string' && !is_string($parameters[$name])) {
                    $errors[] = [
                        'message' => sprintf('Parameter "%s" must be a string.', $name),
                    ];
                }
                if (($options['type'] === 'int' || $options['type'] === 'integer') && !is_numeric($parameters[$name])) {
                    $errors[] = [
                        'message' => sprintf('Parameter "%s" must be an integer.', $name),
                    ];
                }
                if ($options['type'] === 'array' && !is_array($parameters[$name])) {
                    $errors[] = [
                        'message' => sprintf('Parameter "%s" must be an array.', $name),
                    ];
                }
            }
        }

        if ($this->isStrictMode === true) {
            $extraParams = array_diff_key($parameters, $this->rules);
            if (!empty($extraParams)) {
                foreach ($extraParams as $name => $unused) {
                    $errors[] = [
                        'message' => sprintf('Invalid (superfluous) parameter: "%s"', $name),
                    ];
                }
            }
        }

        if (!empty($errors)) {
            return $errors;
        } else {
            return true;
        }
    }

    /**
     * Parse a give ruleset into a desired format.
     *
     * @param  array $rules The rules to parse.
     * @return array
     */
    private function parseRules(array $rules)
    {
        $parameters = [];
        foreach ($rules as $name => $options) {
            $parameters[$name] = [
                'required'        => isset($options['required']) ? boolval($options['required']) : false,
                'type'            => isset($options['type']) ? $options['type'] : 'string',
                'empty'           => isset($options['empty']) ? boolval($options['empty']) : null,
                'callback'        => isset($options['callback']) && is_callable($options['callback']) ? $options['callback'] : null,
                'callbackMessage' => isset($options['callbackMessage']) ? $options['callbackMessage'] : null,
            ];
        }
        return $parameters;
    }
}
