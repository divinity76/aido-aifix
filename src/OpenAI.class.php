<?php

declare(strict_types=1);

namespace aido\OpenAI;

abstract class Openai_input {} //  
enum Role: string
{
    case developer = 'developer';
    case system = 'system';
    case user = 'user';
    case assistant = 'assistant';
};
class OpenAi_input_text extends Openai_input implements \JsonSerializable
{
    public function __construct(
        public Role $role,
        public string $text
    ) {
        //
    }
    public function jsonSerialize(): array
    {
        return [
            "type" => "input_text",
            "role" => $this->role->value,
            "text" => $this->text,
        ];
    }
}
class OpenAI
{
    private $model = 'o3-mini';
    // Store tools as an associative array keyed by tool name.
    private array $tools = [];
    private array $tool_callbacks = [];
    private array $input = [];
    public function storeInput(Openai_input $input)
    {
        // 
        $this->input[] = $input;
    }
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
        $reflection = new \ReflectionFunction($handler);
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
                $argumentDescriptionAttribute = $reflectionParameter->getAttributes(\ArgumentDescription::class);
                if (count($argumentDescriptionAttribute) !== 1) {
                    throw new \Exception('ArgumentDescription attribute is required for all parameters except the first one. ' . $reflectionParameter->getName() . ' is missing the attribute.');
                }
                $argumentDescriptionAttribute = $argumentDescriptionAttribute[0];
                $argumentDescriptionAttributeArguments = $argumentDescriptionAttribute->getArguments();
                [$argumentDescription, $argumentExample] = $argumentDescriptionAttributeArguments;
                $argumentDescription .= ". Example: ";
                if (is_bool($argumentExample)) {
                    $argumentDescription .= $argumentExample ? "true" : "false";
                } else {
                    $argumentDescription .= $argumentExample;
                }
                $parameters['properties'][$argumentName] = [
                    "type" => $argumentType,
                    "description" => $argumentDescription,
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
            "strict" => false, // true seems to make the AI noticably slower/dumber? 
            "parameters" => $parameters,
        ];
        $this->tools[] = $tool;
        $this->tool_callbacks[$name] = $handler;
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
        } catch (\JsonException $e) {
            var_dump(["response" => $response]);
            throw $e;
        }
        return $response;
    }


    /**
     * Create a response using the OpenAI API.
     * 
     * @param string $input The input string to send to the OpenAI API.
     * @return string The response from the OpenAI API.
     */
    public function createResponse(): string
    {
        $empty = true;
        if (!empty($systemInput)) {
            $empty = false;
            $this->input_messages[] = [
                "role" => "system",
                "content" => $systemInput,
            ];
        }
        if (!empty($this->tools)) {
            $empty = false;
            $this->input_messages[] = [
                "role" => "system",
                "content" => "You have access to the following tools: " . json_encode($this->summarizeTools()),
            ];
        }
        if ($empty) {
            throw new \LogicException("No input provided. At least one of systemInput or userInput must be provided.");
        }
        unset($systemInput, $userInput, $empty);
    }
}
