#!/usr/bin/env php
<?php

declare(strict_types=1);
require_once __DIR__ . DIRECTORY_SEPARATOR . 'common.inc.php';

$openai = new OpenAI(get_openai_api_key());
$openai->setModel(pick_ai_model());

require_once __DIR__ . DIRECTORY_SEPARATOR . 'ai_tools.php';

$enable_gui = false;
if ($enable_gui) {
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'ai_tools_gui.php';
}

$args = array_slice($argv, 1);
if (empty($args)) {
    fwrite(STDERR, "Usage: aido \"your instructions here\"\n");
    exit(1);
}
$userInstructions = implode(" ", $args);

// Combine system instructions into a single HEREDOC block
$systemInstructions = <<<'EOT'
You are "aido", an automated assistant designed for proactive AI-driven development tasks.

Core Responsibilities:
- Accurately interpret and execute user instructions provided in natural language.
- Proactively infer sensible defaults for minor ambiguities (e.g., formatting, file structure, naming conventions).
- Only request clarification using the 'ask_user' tool if significant ambiguities arise.

Practical Development Tasks:
- Generate and edit code, create files, install packages, and execute necessary commands.
- Validate all actions clearly and confirm task completion explicitly to the user.
- Utilize the 'file_put_contents' tool to update files as required.

Execution Guidelines:
1. Precisely execute given instructions.
2. Leverage all available tools to complete tasks efficiently.
3. Immediately invoke the 'ask_user' tool for necessary clarifications and await response (avoid plaintext questions).
4. Clearly outline missing functionality or context and abort the process if necessary.
5. Update files exclusively via the 'file_put_contents' tool (do not output file content directly).
EOT;

// Debugging output
var_dump(compact('systemInstructions', 'userInstructions'));

// Generate response using OpenAI API
$response = $openai->createResponse($systemInstructions, $userInstructions);
dd($response);
