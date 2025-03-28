#!/usr/bin/env php
<?php

declare(strict_types=1);
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'common.inc.php');

$openai = new OpenAI(get_openai_api_key());
$openai->setModel(pick_ai_model());

require_once(__DIR__ . DIRECTORY_SEPARATOR . 'ai_tools.php');

$args = $argv;
array_shift($args);
if (empty($args)) {
    fwrite(STDERR, "Usage: aifix failing thing\n");
    exit(1);
}
$problem_data = run_input_command($args);
$problem_data_string = return_var_dump($problem_data);
$systemInstructions = <<<'EOT'
You are "aifix," an automated troubleshooting assistant designed to diagnose and resolve broken commands or errors.
- Accurately analyze provided error messages and context to identify the root cause.
- Act decisively to apply and verify solutions (e.g., fixing code, adjusting environments, installing packages).
- Request clarification explicitly using the 'ask_user' tool only when required context or permissions are missing.
- After applying fixes, explicitly verify and confirm resolution to the user.
- If files needs to be created or modified, use the 'file_put_contents' tool.
- Prioritize minimal, efficient corrections.
EOT;
$userMessage = <<<EOT
Please analyze and resolve the following issue using available tools as needed:

Issue Details:
{$problem_data_string}

Guidelines:
1. Fix the issue accurately based on provided problem data.
2. Use tools ('file_put_contents', 'ask_user') to complete the task.
3. Save any necessary changes and ensure the command executes successfully afterward.
4. Only request clarification with 'ask_user' if essential for task completion.
5. If unable to proceed due to missing context, clearly describe the issue.
EOT;


dd($openai->createResponse($systemInstructions, $userMessage));

