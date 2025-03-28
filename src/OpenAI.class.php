<?php

declare(strict_types=1);
class OpenAI
{
    private $model = 'o3-mini';
    // Store tools as an associative array keyed by tool name.
    private array $tools = [];
    private array $tool_callbacks = [];
    private array $input_messages = [];
    private $apiUrl = 'https://api.openai.com/v1/responses';
    public function setModel(string $model)
    {
        $aliases = array(
            '4o' => 'gpt-4o',
            '4omini' => 'gpt-4o-mini',
            '4o-mini' => 'gpt-4o-mini',
            'o3mini' => 'o3-mini',
        );
        $model = $aliases[$model] ?? $model;
        $this->model = $model;
    }
    public function __construct(
        #[\SensitiveParameter]
        private string $apiKey
    ) {
        //
    }

    private bool $webSearchEnabled = false;
    /**
     * Configures or retrieves the current web search integration state.
     *
     * When called with null, it returns whether web search is enabled.
     * When passed a boolean value, it sets the state accordingly:
     * - true: enables web search and adds the tool if not already present.
     * - false: disables web search and removes all instances of the tool.
     *
     * @param bool|null $state True to enable, false to disable, or null to query the current state.
     * @return bool|void Returns the current state if no boolean is provided.
     */
    public function configureWebSearch(?bool $state = null)
    {
        if ($state === null) {
            return $this->webSearchEnabled;
        }

        if ($state) {
            if (!$this->webSearchEnabled) {
                $this->tools[] = [
                    "type" => "web_search_preview"
                ];
                $this->webSearchEnabled = true;
            }
        } else {
            $keysToUnset = [];
            foreach ($this->tools as $key => $tool) {
                if ($tool['type'] === 'web_search_preview') {
                    $keysToUnset[] = $key;
                }
            }
            if (!empty($keysToUnset)) {
                foreach ($keysToUnset as $key) {
                    unset($this->tools[$key]);
                }
                $this->tools = array_values($this->tools);
            }
            $this->webSearchEnabled = false;
        }
        return $this->webSearchEnabled;
    }
    /**
     * Register a tool with its own handler.
     *
     * @param string   $name        The name of the tool.
     * @param string   $description Description of the tool.
     * @param array    $parameters  The JSON schema parameters.
     * @param callable $handler     The function to execute for this tool.
     */
    public function addTool(string $name, string $description, callable $handler)
    {
        if (isset($this->tool_callbacks[$name])) {
            throw new \LogicException("Tool already registered: " . $name);
        }
        // example:
        $tool = [
            "type" => "function",
            "name" => $name,
            "description" => $description,
            "strict" => true,
        ];
        $reflection = new ReflectionFunction($handler);
        $parameters = [
            "type" => "object",
            "properties" => [],
            "required" => [],
        ];
        $reflectionParameters = $reflection->getParameters();
        foreach ($reflectionParameters as $parameterIndex => $reflectionParameter) {
            if ($parameterIndex === 0) {
                continue; // Skip the first parameter (the handler name)
            }
            $argumentName = $reflectionParameter->getName();
            $argumentType = $reflectionParameter->getType()->getName();
            $isRequired = !$reflectionParameter->isOptional();
            $typeMap = [
                "int" => "integer",
                "bool" => "boolean",
            ];
            $argumentType = $typeMap[$argumentType] ?? $argumentType;
            if (in_array($argumentType, ['string', 'integer', 'boolean'], true)) {
                $argumentDescriptionAttribute = $reflectionParameter->getAttributes(ArgumentDescription::class);
                if (count($argumentDescriptionAttribute) !== 1) {
                    throw new Exception('ArgumentDescription attribute is required for all parameters except the first one. ' . $reflectionParameter->getName() . ' is missing the attribute.');
                }
                $argumentDescriptionAttribute = $argumentDescriptionAttribute[0];
                $argumentDescriptionAttributeArguments = $argumentDescriptionAttribute->getArguments();
                [$description, $example] = $argumentDescriptionAttributeArguments;
                $description .= ". Example: ";
                if (is_bool($example)) {
                    $description .= $example ? "true" : "false";
                } else {
                    $description .= $example;
                }
                $parameters['properties'][$argumentName] = [
                    "type" => $argumentType,
                    "description" => $description,
                ];
            } else {
                throw new \LogicException("Todo, implement type: " . $argumentType); // https://platform.openai.com/docs/api-reference/responses/create
            }
            if ($isRequired) {
                $parameters['required'][] = $argumentName;
            }
        }
        $parameters['properties'] = (object)$parameters['properties'];
        $tool = [
            "type" => "function",
            "name" => $name,
            "description" => $description,
            "parameters" => $parameters,
        ];
        $this->tools[] = $tool;
        $this->tool_callbacks[$name] = $handler;
    }

    /**
     * Create a response using the OpenAI API.
     * 
     * @param string $input The input string to send to the OpenAI API.
     * @return string The response from the OpenAI API.
     */
    public function createResponse(string $systemInput, string $userInput): string
    {
        $this->input_messages[] = [
            "role" => "system",
            "content" => $systemInput,
        ];
        $this->input_messages[] = [
            "role" => "user",
            "content" => $userInput,
        ];
        $returnText = null;
        $iterations = 0;
        while (true) {
            $iterations += 1;
            echo "OpenAI: Iteration: " . $iterations . "\n";
            if ($iterations > 100) {
                throw new \LogicException("Too many iterations: $iterations"); // something is probably wrong?
            }
            $queryData = [
                "model" => $this->model,
                "store" => false,
                "text" => [
                    "format" => [
                        "type" => "json_schema",
                        'name' => 'model_response',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                // 'input_text' => [
                                //     'type' => 'string',
                                //     'description' => 'The text input provided by the human.',
                                // ],
                                'response_text' => [
                                    'type' => 'string',
                                    'description' => 'The response',
                                ],
                                // 'confidence_score' => [
                                //     'type' => 'number',
                                //     'description' => 'The confidence score of the model response, ranging from 0 to 1.',
                                // ],
                                // 'timestamp' => [
                                //     'type' => 'string',
                                //     'description' => 'The timestamp of when the response was generated.',
                                // ],
                            ],
                            'required' => [
                                // 'input_text',
                                'response_text',
                                // 'confidence_score',
                                // 'timestamp',
                            ],
                            'additionalProperties' => false,
                        ],
                        'strict' => true,
                    ],
                ],
            ];
            if (0 && str_starts_with($this->model, 'o')) {
                $queryData['reasoning'] = [
                    'effort' => 'high',
                ];
            }
            $queryData['input'] = $this->input_messages;
            if (!empty($this->tools)) {
                $queryData['tools'] = $this->tools;
                $queryData['tool_choice'] = 'auto';
            }
            $response = $this->curlJson($this->apiUrl, [
                CURLOPT_POSTFIELDS => $queryData,
            ]);
            if (isset($response['error'])) {
                var_dump($response);
                throw new RuntimeException("OpenAI API error: " . $response['error']['message']);
            }
            $isLastIteration = true;
            foreach ($response['output'] as $output) {
                $this->input_messages[] = $output;
                $outputType = $output['type'];
                if ($outputType === "function_call") {
                    $isLastIteration = false;
                    if (false) {
                        $output = array(
                            'type' => 'function_call',
                            'id' => 'fc_67e5e495f65c8191981da6bf097900aa0247c61d2b60896a',
                            'call_id' => 'call_bvjd1FZyOiXM8maeC1dZ2YWD',
                            'name' => 'ls',
                            'arguments' => '{\"path\":\".\"}',
                            'status' => 'completed',
                        );
                    }
                    $toolName = $output['name'];
                    $arguments = json_decode($output['arguments'], true, 512, JSON_THROW_ON_ERROR);
                    $handler = $this->tool_callbacks[$toolName] ?? null;
                    if ($handler === null) {
                        var_dump(["type" => $outputType, "output" => $output]);
                        throw new \LogicException("Handler not found for tool: " . $toolName);
                    }
                    $result = ($handler)($toolName, ...$arguments);
                    $this->input_messages[] = [
                        "type" => "function_call_output",
                        "call_id" => $output['call_id'],
                        "output" => json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR),
                    ];
                } elseif ($outputType === "message") {
                    if (false) {
                        $output = array(
                            'type' => 'message',
                            'id' => 'msg_67e602d22a2c8191a16ff85fe06c8e87080bc1e07f35cddf',
                            'status' => 'completed',
                            'role' => 'assistant',
                            'content' =>
                            array(
                                0 =>
                                array(
                                    'type' => 'output_text',
                                    'text' => '{\"response_text\":\"The current directory contains the following files:\\\n\\\\n- file1.txt\\\\n- file2.txt\"}',
                                    'annotations' =>
                                    array(),
                                ),
                            ),
                        );
                    }
                    $status = $output['status'];
                    if ($status !== 'completed') {
                        var_dump(["outputType" => $outputType, "output" => $output]);
                        throw new \LogicException("Unknown message status: " . $status);
                    }
                    $isLastIteration = true;
                    if (count($output['content']) !== 1) {
                        var_dump(["outputType" => $outputType, "output" => $output]);
                        throw new \LogicException("multi message count not yet implemented: " . json_encode($output['content']));
                    }
                    if (!empty($output['content'][0]['annotations'])) {
                        var_dump(["outputType" => $outputType, "output" => $output]);
                        throw new \LogicException("message annotations not yet implemented: " . json_encode($output['content'][0]['annotations']));
                    }
                    $content = $output['content'][0];
                    $contentType = $content['type'];
                    if ($contentType !== 'output_text') {
                        var_dump(["contentType" => $contentType, "output" => $output]);
                        throw new \LogicException("Unknown content type: " . $contentType);
                    }
                    $text = $content['text'];
                    $text = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                    if (false) {
                        $text = array(
                            'response_text' => 'The current directory contains the following files:
            - file1.txt
            - file2.txt',
                        );
                    }
                    if ($returnText !== null) {
                        var_dump(["old_returnText" => $returnText, "new_returnText" => $text["response_text"]]);
                        throw new \LogicException("returnText already set: " . $returnText);
                    }
                    $returnText = $text["response_text"];
                    unset($text["response_text"]);
                    if (!empty($text)) {
                        throw new \LogicException("Unknown data in text: " . var_export($text, true));
                    }
                } elseif ($outputType === "reasoning") {
                    // reasoning output indicates the model is still processing
                    $isLastIteration = false;
                    // oterwise just ignore reasoning output..
                } else {
                    var_export($output);
                    echo "\n";
                    var_dump(["outputType" => $outputType, "output" => $output]);
                    throw new \LogicException("Unknown output type: " . $outputType);
                }
            }
            if ($isLastIteration) {
                if ($returnText === null) {
                    // should be impossible
                    throw new \LogicException("returnText is null and isLastIteration is true");
                }
                return $returnText;
            }
        }
    }

    /**
     * Generate a structured summary of installed tools.
     *
     * @return array Structured summary of tools.
     */
    public function summarizeTools(): array
    {
        $summary = [];
        foreach ($this->tools as $tool) {
            $toolSummary = [
                'type' => $tool['type'] ?? null,
                'name' => $tool['name'] ?? null,
                'description' => $tool['description'] ?? null,
                'parameters' => $tool['parameters'] ?? null
            ];
            $summary[] = $toolSummary;
        }
        return $summary;
    }

    private function curlJson(string $url, array $opts = [])
    {
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_RETURNTRANSFER] = true;
        $ops[CURLOPT_ENCODING] = '';
        if (!empty($opts[CURLOPT_HTTPHEADER])) {
            throw new \LogicException("modifying headers is not implemented.");
        }
        $headers = [
            "Authorization: Bearer " . $this->apiKey,
        ];
        if (!empty($opts[CURLOPT_POSTFIELDS])) {
            $opts[CURLOPT_POST] = true;
            $headers[] = "Content-Type: application/json";
            $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR;
            if (0) {
                $cmd = [
                    "curl",
                    quoteshellarg($url),
                ];
                foreach ($headers as $header) {
                    $cmd[] = "-H " . quoteshellarg($header);
                }
                $flags |= JSON_PRETTY_PRINT;
                $cmd[] = "-d " . quoteshellarg(json_encode($opts[CURLOPT_POSTFIELDS], $flags));
                var_dump(implode(" ", $cmd));
            }
            $opts[CURLOPT_POSTFIELDS] = json_encode($opts[CURLOPT_POSTFIELDS], $flags);
        }
        $opts[CURLOPT_HTTPHEADER] = $headers;

        $ch = curl_init();
        if (!curl_setopt_array($ch, $opts)) {
            throw new RuntimeException("curl_setopt() failed: " . curl_errno($ch) . ": " . curl_error($ch) . " strerror: " . curl_strerror(curl_errno($ch)));
        }
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        if ($errno !== CURLE_OK) {
            throw new RuntimeException("curl_exec() failed: " . $errno . ": " . curl_error($ch) . " strerror: " . curl_strerror($errno));
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode !== 200) {
            throw new RuntimeException("HTTP error: " . $httpCode . ": " . $response);
        }
        curl_close($ch);
        try {
            $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            var_dump(["response" => $response]);
            throw $e;
        }
        return $response;
    }
}
