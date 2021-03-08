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
     * @see https://www.php.net/manual/en/filter.filters.validate.php
     * Having multiple flags:
     *      'flags' => FILTER_FLAG_STRIP_LOW | FILTER_FLAG_ENCODE_HIGH
     * @return array
     */
    protected function acceptedTypes()
    {
        return [
            'boolean'   => [
                'filter'  => FILTER_VALIDATE_BOOLEAN,
                'options' => [
                    'options' => [],
                    // If FILTER_NULL_ON_FAILURE is set, FALSE is returned only for "0", "false",
                    // "off", "no", and "", and NULL is returned for all non-boolean values.
                    'flags' => FILTER_NULL_ON_FAILURE
                ]
            ],
            'string'   => [
                'filter'  => FILTER_SANITIZE_STRING,
                'options' => [
                    'options' => []
                ]
            ],
            'email'    => [
                'filter'  => FILTER_VALIDATE_EMAIL,
                'options' => [
                    'options' => []
                ]
            ],
            'int'    => [
                'filter'  => FILTER_VALIDATE_INT,
                'options' => [
                    'options' => []
                ]
            ],
            'float'    => [
                'filter'  => FILTER_VALIDATE_FLOAT,
                'options' => [
                    'options' => []
                ]
            ],
            'callback' => [
                'filter'  => FILTER_CALLBACK,
                'options' => [
                    'options' => []
                ]
            ],
            'array'    => [
                'filter'  => FILTER_CALLBACK,
                'options' => [
                    'options' => [$this, 'validateArray']
                ]
            ],
            'url' => [
                'filter' => FILTER_VALIDATE_URL,
                'options' => [

                ]
            ],
            'regexp' => [
                'filter' => FILTER_VALIDATE_REGEXP,
                'options' => [
                    'regexp' => ''
                ]
            ],
            'unsafe'   => [
                'filter'  => FILTER_CALLBACK,
                'options' => [
                    'options' => [$this, 'validateUnsafe']
                ]
            ]
        ];
    }

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

            if (is_array($options['values']) && !empty($options['values']) && !in_array($parameters[$name], $options['values'])) {
                $errors[] = [
                    'message' => sprintf('Parameter "%s" must be one of "%s".', $name, implode('|', $options['values']))
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

                $type = isset($options['type']) ? $options['type'] : 'unsafe';
                if (!isset($this->acceptedTypes()[$type])) {
                    $errors[] = [
                        'message' => sprintf('Invalid type "%s" provided for "%s".', $type, $name)
                    ];
                } else {
                    $filterType = $this->acceptedTypes()[$type];
                    if (isset($options['typeOptions'])) {
                        $filterType['options'] = array_merge($filterType['options'], $options['typeOptions']);
                    }

                    $val = filter_var($parameters[$name], $filterType['filter'], $filterType['options']);

                    if (!$val) {
                        $errors[] = [
                            'message' => sprintf('Parameter "%s" must be of type "%s".', $name, $type)
                        ];
                    }
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
                'values'          => isset($options['values']) ? $options['values'] : null,
                'typeOptions'     => [],
            ];
        }
        return $parameters;
    }

    /**
     * @param $array
     * @return array|bool
     */
    public function validateArray($array)
    {
        if (!$array) {
            return false;
        }

        return is_array($array) ? $array : false;
    }

    /**
     * Default callback validation when no type defined.
     *
     * @param $value
     * @return mixed
     */
    public function validateUnsafe($value)
    {
        return $value;
    }
}
