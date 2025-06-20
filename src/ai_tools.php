<?php

declare(strict_types=1);

function AiDirFixer(string $path): string
{
    // for unknown reasons, the ai (at least 4o-mini) sometimes include trailing whitespace in the path...
    // Technically, unix paths can have trailing whitespace, but it's extremely rare.
    // We'll choose not to support it.
    // it's far more likely to be a AI mistake, than an actual directory-name-with-space.
    $path = trim($path);
    if (str_starts_with($path, '~')) {
        $path = getUserHomeDir() . substr($path, 1);
    }
    // Ensure cross-platform directory separators. On Windows, replace forward slashes with DIRECTORY_SEPARATOR
    if (DIRECTORY_SEPARATOR !== '/') {
        $path = str_replace('/', DIRECTORY_SEPARATOR, $path);
    }
    return $path;
}

/*
$openai->addTool(
    'get_current_weather',
    'Get the current weather in a given location',
    [
        'location' => ['type' => 'string', 'description' => 'The city and state, e.g. Boston, MA'],
        'unit' => ['type' => 'string', 'enum' => ['celsius', 'fahrenheit']]
    ],
    function ($toolName, $location, $unit) {
        // In a real scenario, you would call a weather API here.
        // For this example, we just return a static response.
        return js_encode([
            'temperature' => '42' . ($unit === 'celsius' ? '°C' : '°F'),
            'condition' => 'Cloudy',
            'notes' => ($unit === 'celsius' ?  'It\'s extremely hot for this time of year.' : 'It\'s a bit chilly.')
        ]);
    }
);
*/
$openai->addTool(
    'pwd',
    'Get the current working directory',
    [
        // No parameters for this tool.
    ],
    function ($toolName) {
        return js_encode([
            'directory' => getcwd()
        ]);
    }
);
$openai->addTool(
    'ls',
    'List the contents of a directory',
    [
        'path' => ['type' => 'string', 'description' => 'The directory to list']
    ],
    function ($toolName, $path) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        if (!is_dir($path)) {
            return js_encode([
                'error' => 'Not a valid directory: ' . var_export($path, true)
            ]);
        }
        var_dump(["toolName" => $toolName, "path" => $path]);
        $files = scandir($path);
        return js_encode([
            'files' => array_values(array_diff($files, ['.', '..']))
        ]);
    }
);
$openai->addTool(
    'recursive_ls_paginated',
    'Recursively list the contents of a directory with pagination (page starts at 0)',
    [
        'path' => [
            'type' => 'string',
            'description' => 'The directory to list'
        ],
        'current_page' => [
            'type' => 'integer',
            'description' => 'Page number (starting from 0)'
        ],
        'page_size' => [
            'type' => 'integer',
            'description' => 'Number of results per page (max 100)'
        ]
    ],
    function ($toolName, $path, $current_page, $page_size) {
        var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "current_page" => $current_page,
            "page_size" => $page_size
        ]);

        // Fix trailing whitespace in the path
        $path = AiDirFixer($path);
        if (!is_dir($path)) {
            return js_encode([
                'error' => 'Not a valid directory: ' . var_export($path, true)
            ]);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));

        // Calculate the offset based on page starting from 0
        $offset = $current_page * $page_size;
        $i = 0;

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                continue;
            }
            if ($i < $offset) {
                $i++;
                continue;
            }
            if ($file->isDir()) {
                $files[] = $file->getPathname();
            } else {
                //$files[] = $file->getFilename();
                $files[] = $file->getPathname();
            }
            $i++;

            if (count($files) > $page_size) {
                break;
            }
        }
        $isLastPage = count($files) < $page_size;
        if (!$isLastPage) {
            unset($files[array_key_last($files)]);
        }
        $ret = [
            'files' => $files,
            'current_page' => $current_page,
            'page_size' => $page_size,
            'is_last_page' => $isLastPage
        ];
        return js_encode($ret);
    }
);

$openai->addTool(
    'cd',
    'Change the current working directory',
    [
        'path' => ['type' => 'string', 'description' => 'The directory to change to']
    ],
    function ($toolName, $path) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        if (!is_dir($path)) {
            // for unknown reasons, the ai (at least 4o-mini) sometimes include trailing whitespace in the path...
            $path = rtrim($path);
            if (!is_dir($path)) {
                return js_encode([
                    'error' => 'Not a valid directory: ' . var_export($path, true)
                ]);
            }
        }
        $success = chdir($path);
        $ret = [
            'success' => $success,
            'current_directory' => getcwd()
        ];
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return js_encode($ret);
    }
);
$openai->addTool(
    'file_get_contents',
    'Read the contents of a file',
    [
        'path' => ['type' => 'string', 'description' => 'The file to read'],
        'offset' => ['type' => 'integer', 'description' => 'offset to start reading from, default 0'],
        'length' => ['type' => 'integer', 'description' => 'length to read']
    ],
    function ($toolName, $path, $offset = 0, $length = null) {
        $length = $length ?? PHP_INT_MAX;
        // Invalid 'messages[4].content': string too long. Expected a string with maximum length 1048576, but got a string with length 1056035 instead.
        // This model's maximum context length is 200000 tokens. However, your messages resulted in 257907 tokens (257370 in the messages, 537 in the functions). Please reduce the length of the messages or functions.
        $offset = filter_var($offset, FILTER_VALIDATE_INT);
        $length = filter_var($length, FILTER_VALIDATE_INT);
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "offset" => $offset,
            "length" => $length
        ]);
        if (!is_file($path)) {
            return js_encode([
                'error' => 'Not a valid file: ' . var_export($path, true)
            ]);
        }
        $content = file_get_contents($path, false, null, $offset, $length);
        if ($content === false) {
            return js_encode([
                //'file_path' => $path,
                'error' => 'Failed to read file: ' . var_export(error_get_last(), true)
            ]);
        }
        return js_encode([
            'contents' => $content,
            'file_size' => filesize($path),
        ]);
    }
);
$openai->addTool(
    'file_put_contents',
    'Write contents to a file',
    [
        'path' => ['type' => 'string', 'description' => 'The file to write to'],
        'contents' => ['type' => 'string', 'description' => 'The contents to write']
    ],
    function ($toolName, $path, $contents) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "contents" => $contents
        ]);
        $success = @file_put_contents($path, $contents);
        $ret = [
            'success' => $success,
            'file_path' => $path
        ];
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return js_encode($ret);
    }
);

$openai->addTool(
    'file_exists',
    'Check if a file exists',
    [
        'path' => ['type' => 'string', 'description' => 'The file to check']
    ],
    function ($toolName, $path) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        $exists = file_exists($path);
        return js_encode([
            'exists' => $exists,
            'file_path' => $path
        ]);
    }
);
$openai->addTool(
    'file_delete',
    'Delete a file',
    [
        'path' => ['type' => 'string', 'description' => 'The file to delete']
    ],
    function ($toolName, $path) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        $success = unlink($path);
        $ret = [
            'success' => $success,
            'file_path' => $path
        ];
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return js_encode($ret);
    }
);
$openai->addTool(
    'file_rename',
    'Rename a file',
    [
        'old_path' => ['type' => 'string', 'description' => 'The file to rename'],
        'new_path' => ['type' => 'string', 'description' => 'The new file path']
    ],
    function ($toolName, $old_path, $new_path) {
        $old_path = AiDirFixer($old_path);
        $new_path = AiDirFixer($new_path);
        var_dump([
            "toolName" => $toolName,
            "old_path" => $old_path,
            "new_path" => $new_path
        ]);
        $success = rename($old_path, $new_path);
        $ret = [
            'success' => $success,
            'old_path' => $old_path,
            'new_path' => $new_path
        ];
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return js_encode($ret);
    }
);
$openai->addTool(
    'execute_shell_command',
    'Execute a shell command with optional timeout and stdin support',
    [
        'command' => [
            'type' => 'string',
            'description' => 'The shell command to execute'
        ],
        'stdin' => [
            'type' => 'string',
            'description' => 'The standard input for the command'
        ],
        'timeout' => [
            'type' => 'number',
            'description' => 'Max execution time in seconds before killing the process (0 for no timeout. Default: 200)',
        ],
    ],
    function ($toolName, $command, $stdin = "", $timeout = 200) {
        var_dump([
            "toolName" => $toolName,
            "command" => $command,
            "stdin" => $stdin,
            "timeout" => $timeout
        ]);
        if (empty($timeout)) {
            $timeout = 3600;
        }
        // Descriptor spec now includes a pipe for stdin.
        $descriptor_spec = [
            0 => ['pipe', 'rb'], // stdin
            1 => ['pipe', 'wb'], // stdout
            2 => ['pipe', 'wb'], // stderr
        ];

        $pipes = [];
        $proc = proc_open($command, $descriptor_spec, $pipes);
        if (!is_resource($proc)) {
            return js_encode([
                'error' => 'Failed to start process: ' . var_export($command, true) . ' ' .
                    var_export(error_get_last(), true)
            ]);
        }

        // Write provided stdin (if any) and then close the pipe.
        if (is_string($stdin) && strlen($stdin) > 0) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        unset($pipes[0]);

        // Set stdout and stderr pipes to non-blocking mode.
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout_log = '';
        $stderr_log = '';
        $combined_log = '';
        $timed_out = false;
        $start_time = microtime(true);

        do {
            $status = proc_get_status($proc);

            // Check for timeout
            if ((microtime(true) - $start_time) >= $timeout) {
                // Kill process using SIGKILL (signal 9)
                proc_terminate($proc, 9);
                $timed_out = true;
                break;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;
            $tv_sec = 0;
            $tv_usec = 100000; // 100ms timeout for stream_select

            $changed = stream_select($read, $write, $except, $tv_sec, $tv_usec);
            if ($changed === false) {
                return js_encode(['error' => 'stream_select failed']);
            }

            foreach ($read as $stream) {
                // Keep reading until no more data is available on this iteration.
                while (($data = fread($stream, 8192)) !== false && $data !== '') {
                    if ($stream === $pipes[1]) {
                        $stdout_log .= $data;
                        var_dump(["stdout" => $data]);
                    } elseif ($stream === $pipes[2]) {
                        $stderr_log .= $data;
                        var_dump(["stderr" => $data]);
                    }
                    $combined_log .= $data;
                }
            }
        } while ($status['running']);

        // Drain any remaining output after the process has ended.
        foreach ([$pipes[1], $pipes[2]] as $pipe) {
            while (!feof($pipe)) {
                $data = fread($pipe, 8192);
                if ($data === false || $data === '') {
                    break;
                }
                if ($pipe === $pipes[1]) {
                    $stdout_log .= $data;
                    var_dump(["stdout" => $data]);
                } else {
                    $stderr_log .= $data;
                    var_dump(["stderr" => $data]);
                }
                $combined_log .= $data;
            }
        }

        // Close stdout and stderr pipes.
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exit_code = proc_close($proc);

        return js_encode([
            'return_code'     => $exit_code,
            'stdout'          => $stdout_log,
            'stderr'          => $stderr_log,
            'combined_output' => $combined_log,
            'timed_out'       => $timed_out,
            'duration'        => round(microtime(true) - $start_time, 3)
        ]);
    }
);

$openai->addTool(
    'execute_python_script',
    'Execute a Python script',
    [
        'script' => ['type' => 'string', 'description' => 'The Python script to execute'],
        'stdin'  => ['type' => 'string', 'description' => 'The standard input for the script'],
    ],
    function ($toolName, $script, $stdin = "") {
        var_dump([
            "toolName" => $toolName,
            "script"   => $script,
            "stdin"    => $stdin
        ]);
        // Write the Python script to a temporary file.
        $tempScript = tempnam(sys_get_temp_dir(), 'py_script_');
        rename($tempScript, $tempScript . '.py');
        $tempScript .= '.py';
        file_put_contents($tempScript, $script);

        // Prepare handles to capture stdout and stderr.
        $stdout_handle = tmpfile();
        $stderr_handle = tmpfile();
        $descriptorSpec = [
            0 => ['pipe', 'rb'], // stdin
            1 => $stdout_handle, // stdout
            2 => $stderr_handle  // stderr
        ];

        $pipes = [];
        // Build the command string to execute the temporary Python file.
        $command = "python3 " . escapeshellarg($tempScript);
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            unlink($tempScript); // Clean up the temp file.
            return js_encode([
                'error' => 'Failed to execute command: ' . var_export(error_get_last(), true)
            ]);
        }

        if (is_string($stdin) && strlen($stdin) > 0) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        unset($pipes[0]);

        $return_code = proc_close($process);
        rewind($stdout_handle);
        rewind($stderr_handle);
        $stdout = stream_get_contents($stdout_handle);
        $stderr = stream_get_contents($stderr_handle);
        fclose($stdout_handle);
        fclose($stderr_handle);
        unlink($tempScript);

        // Build the result array.
        $result = [
            'return_code' => $return_code,
            'stdout'      => $stdout,
            'stderr'      => $stderr,
        ];
        return js_encode($result);
    }
);

$openai->addTool(
    'execute_php_script',
    'Execute a PHP script',
    [
        'script' => ['type' => 'string', 'description' => 'The PHP script to execute'],
        'stdin'  => ['type' => 'string', 'description' => 'The standard input for the script'],
    ],
    function ($toolName, $script, string $stdin) {
        var_dump([
            "toolName" => $toolName,
            "script"   => $script
        ]);
        // Write the PHP script to a temporary file.
        $tempScript = tempnam(sys_get_temp_dir(), 'php_script_');
        rename($tempScript, $tempScript . '.php');
        $tempScript .= '.php';
        file_put_contents($tempScript, $script);

        // Prepare handles to capture stdout and stderr.
        $stdout_handle = tmpfile();
        $stderr_handle = tmpfile();
        $descriptorSpec = [
            0 => ['pipe', 'rb'], // stdin
            1 => $stdout_handle, // stdout
            2 => $stderr_handle  // stderr
        ];

        $pipes = [];
        // Build the command string to execute the temporary PHP file.
        $command = "php " . escapeshellarg($tempScript);
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            unlink($tempScript); // Clean up the temp file.
            return js_encode([
                'error' => 'Failed to execute command: ' . var_export(error_get_last(), true)
            ]);
        }
        if(strlen($stdin) > 0) {
            fwrite($pipes[0], $stdin);
        }
        fclose($pipes[0]);
        unset($pipes[0]);

        $return_code = proc_close($process);
        rewind($stdout_handle);
        rewind($stderr_handle);
        $stdout = stream_get_contents($stdout_handle);
        $stderr = stream_get_contents($stderr_handle);
        fclose($stdout_handle);
        fclose($stderr_handle);
        unlink($tempScript);

        // Build the result array.
        $result = [
            'return_code' => $return_code,
            'stdout'      => $stdout,
            'stderr'      => $stderr,
        ];
        return js_encode($result);
    }
);
$openai->addTool(
    'fetch_url',
    'Fetch the contents of a URL with pagination support',
    [
        'url'  => ['type' => 'string',  'description' => 'The URL to fetch'],
        'page' => ['type' => 'integer', 'description' => 'Optional page number (default: 1)'],
    ],
    function ($toolName, $url, $page = 1) {
        var_dump([
            "toolName" => $toolName,
            "url"      => $url,
            "page"     => $page
        ]);
        $cache_key_prefix = "fetch_url:";
        $cache_key = "$cache_key_prefix$url";
        $cached = Cache::get($cache_key, 1 * 60 * 60);

        if ($cached !== null) {
            $full_contents = $cached['full_contents'];
        } else {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'aifix-bot; bot; curl/' . curl_version()['version'] . "; php/" . PHP_VERSION,
                CURLOPT_ENCODING       => '',
            ]);
            $contents = curl_exec($ch);

            $ret = [];
            $errno = curl_errno($ch);
            if ($errno !== CURLE_OK) {
                $ret['curl_errno']   = $errno;
                $ret['curl_error']   = curl_error($ch);
                $ret['curl_strerror'] = curl_strerror($errno);
            }
            $response_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $ret['response_code'] = $response_code;
            curl_close($ch);

            // Store the full content in cache for later pagination.
            $ret['full_contents'] = $contents;
            Cache::set($cache_key, $ret);
            $full_contents = $contents;
        }

        // Define maximum allowed length per page.
        $maxLength = 1048576; // 1,048,576 characters
        $totalLength = strlen($full_contents);
        $totalPages = (int)ceil($totalLength / $maxLength);

        // Validate the page parameter.
        $page = (int)$page;
        if ($page < 1) {
            $page = 1;
        } elseif ($page > $totalPages) {
            return js_encode([
                "error" => "Invalid page number. There are only $totalPages page(s) available."
            ]);
        }

        $offset = ($page - 1) * $maxLength;
        $paginatedContent = substr($full_contents, $offset, $maxLength);

        // Prepare the response including pagination metadata.
        $response = [
            "contents"    => $paginatedContent,
            "page"        => $page,
            "total_pages" => $totalPages,
            "response_code" => isset($ret['response_code']) ? $ret['response_code'] : 200,
        ];

        return js_encode($response);
    }
);
$openai->addTool(
    'ask_user',
    'Ask the user a question and return their response',
    [
        'question' => [
            'type' => 'string',
            'description' => 'The question to ask the user'
        ]
    ],
    function ($toolName, $question) {
        var_dump([
            "toolName" => $toolName,
            "question" => $question
        ]);
        echo $question . "\n";
        $line = readline("Your response: ");
        if ($line !== false) {
            readline_add_history($line);
        }

        return js_encode(['response' => trim($line)]);
    }
);
