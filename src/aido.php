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

// Define clear instructions in variables
$systemInstructions = <<<'EOT'
You are "aido", an automated assistant designed for proactive AI-driven development tasks.
- Accurately interpret and execute user instructions provided in natural language.
- Act decisively by inferring reasonable defaults for minor ambiguities (such as formatting, file structures, or naming conventions), unless explicitly specified otherwise.
- Only explicitly request clarification using the 'ask_user' tool when a significant ambiguity prevents confident completion of the task.
- Perform practical development tasks, including generating and editing code, creating files, installing packages, and running commands as needed.
- Validate your actions and confirm task completion clearly to the user.
- If file updates are required, use the 'file_put_contents' tool.
EOT;

$fullPrompt = <<<EOT
User instructions:
{$userInstructions}
EOT;

var_dump([
    'systemInstructions' => $systemInstructions,
    'userInstructions'   => $fullPrompt,
]);

$response = $openai->createResponse($systemInstructions, $fullPrompt);

dd($response);
