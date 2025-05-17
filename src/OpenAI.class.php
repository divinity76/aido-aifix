<?php

declare(strict_types=1);
class OpenAI
{
    private $model = 'o4-mini';
    // Store tools as an associative array keyed by tool name.
    private $tools_definitions = [];
    public $tools_handlers = []; // todo should be private. temporarily public for debugging.
    private $messages = [];

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

    /**
     * Register a tool with its own handler.
     *
     * @param string   $name        The name of the tool.
     * @param string   $description Description of the tool.
     * @param array    $parameters  The JSON schema parameters.
     * @param callable $handler     The function to execute for this tool.
     */
    public function addTool($name, $description, $parameters, callable $handler)
    {
        $toolDefinition = [
            'type' => 'function',
            'function' => [
                'name' => $name,
                'description' => $description,
                'parameters' => [
                    'type' => 'object',
                    'properties' => (object)$parameters,
                    'required' => array_keys($parameters)
                ]
            ]
        ];
        $this->tools_definitions[] = $toolDefinition;
        $this->tools_handlers[$name] = $handler;
    }

    /**
     * Ask the model a question. It will automatically process any tool calls.
     *
     * @param string $question The user question.
     * @return string          The final answer from the model.
     * @throws Exception       If a tool is called with no registered handler.
     */
    public function ask($question)
    {
        $this->messages[] = ['role' => 'user', 'content' => $question];

        while (true) {
            $response = $this->chatCompletion($this->messages);
            if (1) {
                var_dump($response);
            }
            if (!empty($response['error'])) {
                throw new Exception(var_export($response['error'], true));
            }
            $choice = $response['choices'][0]['message'];
            $toolCalls = $choice['tool_calls'] ?? null;

            if ($toolCalls) {
                foreach ($toolCalls as $toolCall) {
                    $toolName = $toolCall['function']['name'];
                    $arguments = json_decode($toolCall['function']['arguments'], true, 512, JSON_THROW_ON_ERROR);
                    $toolCallId = $toolCall['id'];

                    if (!isset($this->tools_handlers[$toolName])) {
                        throw new Exception("No handler registered for tool {$toolName}");
                    }
                    $handler = $this->tools_handlers[$toolName];
                    //var_dump($toolName, $arguments);
                    if (isset($arguments['.current_page']) && !isset($arguments['current_page'])) {
                        // for some reason, sometimes the AI refers to current_page argument as .current_page... not sure why
                        $arguments['current_page'] = $arguments['.current_page'];
                        unset($arguments['.current_page']);
                    }
                    if ($toolName === 'pwd') {
                        // may try to send argument "pwd"=>""                        
                        $arguments = [];
                    }
                    try {
                        $toolResult = $handler($toolName, ...$arguments);
                    } catch (\Throwable $e) {
                        var_dump(["toolName" => $toolName, "arguments" => $arguments]);
                        throw $e;
                    }
                    // Record the tool call and the tool's response into the message history.
                    $this->messages[] = [
                        'role' => 'assistant',
                        'tool_calls' => [$toolCall]
                    ];
                    $this->messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content' => $toolResult
                    ];
                }
            } else {
                $this->messages[] = $choice;
                return $choice['content'];
            }
        }
    }

    /**
     * Calls the OpenAI chat completions endpoint.
     *
     * @param array $messages The conversation history.
     * @return array          The API response.
     */
    private function chatCompletion($messages)
    {
        $data = [
            'model' => $this->model,
            'messages' => $messages,
            'tool_choice' => 'auto'
        ];

        if (!empty($this->tools_definitions)) {
            assert(array_is_list($this->tools_definitions));
            assert(count($this->tools_definitions) === count($this->tools_handlers));
            $data['tools'] = $this->tools_definitions;
        } else {
            unset($data['tool_choice']);
        }

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $this->apiKey
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS |  JSON_THROW_ON_ERROR));
        $response = curl_exec($ch);
        if ($response === false) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        curl_close($ch);

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }
    public function summarize_tools()
    {
        $tools = [];
        foreach ($this->tools_definitions as $tool) {
            $tools[$tool['function']['name']] = [
                'description' => $tool['function']['description'],
                'parameters' => $tool['function']['parameters']['properties']
            ];
        }
        return $tools;
    }
}
