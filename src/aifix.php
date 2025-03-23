#!/usr/bin/env php
<?php

declare(strict_types=1);
require_once('common.inc.php');

$openai = new OpenAI(get_openai_api_key());
$openai->setModel(pick_ai_model());

require_once('ai_tools.php');

$args = $argv;
array_shift($args);
if (empty($args)) {
    fwrite(STDERR, "Usage: aifix failing thing\n");
    exit(1);
}
$problem_data = run_input_command($args);
if (1) {
    $query = "Please analyze and resolve the following issue using all available tools if needed:\n\n";
    $query .= "Issue Details:\n" . return_var_dump($problem_data) . "\n\n";
    $query .= "Additional Guidelines:\n";
    $query .= "1. Fix the issue accurately based on the provided problem data.\n";
    $query .= "2. Use all available tools to complete the task.\n";
    $query .= "3. Save any changes to disk and re-run the command until it executes successfully.\n";
    $query .= "4. If the task requires any clarifications or confirmation, immediately call the ask_user tool with the appropriate question and wait for a response. Do not include the question as plain text in your answer. If further clarification is needed, abort the process.\n";
    $query .= "5. If additional functionality or context is needed, describe what is missing and abort the process.\n";
    $query .= "6. When a file update is required, call the file_put_contents tool with the correct file path and updated content. Do not output the updated file content in plaintext.";
}
var_dump($query);

dd($openai->ask($query));
