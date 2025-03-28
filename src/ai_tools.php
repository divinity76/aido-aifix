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

/** @var \OpenAI $openai */
$openai->addTool(
    'pwd',
    'Get the current working directory',
    function (string $toolName) {
        return ([
            'directory' => getcwd()
        ]);
    }
);
$openai->addTool(
    'ls',
    'List the contents of a directory',
    function (
        string $toolName,
        #[ArgumentDescription('The directory to list', '~/Documents')]
        string $path
    ) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        if (!is_dir($path)) {
            return ([
                'error' => 'Not a valid directory: ' . var_export($path, true)
            ]);
        }
        $files = scandir($path);
        return ([
            'files' => array_values(array_diff($files, ['.', '..']))
        ]);
    }
);
$openai->addTool(
    'recursive_ls_paginated',
    'Recursively list the contents of a directory with pagination (page starts at 0)',
    function (
        string $toolName,
        #[ArgumentDescription('The directory to list', '~/Documents')]
        string $path,
        #[ArgumentDescription('Page number (starting from 0)', 0)]
        int $current_page,
        #[ArgumentDescription('Number of results per page', 100)]
        int $page_size
    ) {
        var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "current_page" => $current_page,
            "page_size" => $page_size
        ]);

        // Fix trailing whitespace in the path
        $path = AiDirFixer($path);
        if (!is_dir($path)) {
            return ([
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
        return ($ret);
    }
);

$openai->addTool(
    'cd',
    'Change the current working directory',
    function (
        string $toolName,
        #[ArgumentDescription('The directory to change to', '~/Documents')]
        string $path
    ) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        if (!is_dir($path)) {
            // for unknown reasons, the ai (at least 4o-mini) sometimes include trailing whitespace in the path...
            $path = rtrim($path);
            if (!is_dir($path)) {
                return ([
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
        return ($ret);
    }
);
$openai->addTool(
    'file_get_contents',
    'Read the contents of a file',
    function (
        string $toolName,
        #[ArgumentDescription('The file to read', '~/Documents/file.txt')]
        string $path,
        #[ArgumentDescription('The offset to start reading from', 0)]
        int $offset = 0,
        #[ArgumentDescription('The length of the content to read', 1048576)]
        ?int $length = null
    ) {
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
            return ([
                'error' => 'Not a valid file: ' . var_export($path, true)
            ]);
        }
        $content = file_get_contents($path, false, null, $offset, $length);
        if ($content === false) {
            return ([
                //'file_path' => $path,
                'error' => 'Failed to read file: ' . var_export(error_get_last(), true)
            ]);
        }
        return ([
            'contents' => $content,
            'file_size' => filesize($path),
        ]);
    }
);
$openai->addTool(
    'file_put_contents',
    'create, replace, or append to a file',
    function (
        string $toolName,
        #[ArgumentDescription('The file to write to', '~/Documents/file.txt')]
        string $path,
        #[ArgumentDescription('The content to write', 'Hello, world!')]
        string $contents,
        #[ArgumentDescription('Append instead of overwriting', "false")]
        bool $append
    ) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "contents" => $contents
        ]);
        $ret = [];
        $dirname = dirname($path);
        if (!is_dir($dirname)) {
            if (!mkdir($dirname, 0777, true)) {
                return ([
                    'error' => 'Failed to create directory: ' . var_export($dirname, true)
                ]);
            }
            $ret['created_directory'] = $dirname;
        }
        $flags = $append ? FILE_APPEND : 0;
        $flags |= LOCK_EX; // Use exclusive lock
        $written = @file_put_contents($path, $contents, $flags);
        $success = ($written === strlen($contents));
        $ret['success'] = $success;
        $ret['file_path'] = $path;
        $ret['bytes_written'] = $written;
        if (is_file($path)) {
            $ret['file_size'] = filesize($path);
        }
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return ($ret);
    }
);
$openai->addTool(
    'file_patch_contents',
    'Patch contents in a file by byte offset',
    function (
        string $toolName,
        #[ArgumentDescription('The file to patch', '~/Documents/file.txt')]
        string $path,
        #[ArgumentDescription('The offset to patch', 0)]
        int $offset,
        #[ArgumentDescription('The number of bytes to remove from offset', 0)]
        int $bytes_to_remove,
        #[ArgumentDescription('The content to insert at offset', 'Hello, world!')]
        string $contents
    ) {
        $path = AiDirFixer($path);
        if (1) var_dump([
            "toolName" => $toolName,
            "path" => $path,
            "offset" => $offset,
            "bytes_to_remove" => $bytes_to_remove,
            "contents" => $contents
        ]);

        if (!is_file($path)) {
            return ([
                'error' => 'Not a valid file: ' . var_export($path, true)
            ]);
        }

        $fileContents = file_get_contents($path);
        if ($fileContents === false) {
            return ([
                'error' => 'Failed to read file: ' . var_export(error_get_last(), true)
            ]);
        }

        // Use $contents as the new text to insert into $fileContents
        $new_contents = substr_replace($fileContents, $contents, $offset, $bytes_to_remove);

        $success = @file_put_contents($path, $new_contents);
        $ret = [
            'success' => $success,
            'file_path' => $path
        ];
        if (!$success) {
            $ret['error'] = var_export(error_get_last(), true);
        }
        return ($ret);
    }
);
$openai->addTool(
    'file_exists',
    'Check if a file exists',
    function (
        string $toolName,
        #[ArgumentDescription('The file to check', '~/Documents/file.txt')]
        string $path
    ) {
        $path = AiDirFixer($path);
        var_dump([
            "toolName" => $toolName,
            "path" => $path
        ]);
        $exists = file_exists($path);
        return ([
            'exists' => $exists,
            'file_path' => $path
        ]);
    }
);
$openai->addTool(
    'file_delete',
    'Delete a file',
    function (
        string $toolName,
        #[ArgumentDescription('The file to delete', '~/Documents/file.txt')]
        string $path
    ) {
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
        return ($ret);
    }
);
$openai->addTool(
    'file_move',
    'Move or rename a file',
    function (
        string $toolName,
        #[ArgumentDescription('The file to move', '~/Documents/file.txt')]
        string  $old_path,
        #[ArgumentDescription('The new file path', '~/Documents/new_file.txt')]
        string $new_path
    ) {
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
        return ($ret);
    }
);
$openai->addTool(
    'fetch_url',
    'Fetch the contents of a URL with pagination support',
    function (
        string $toolName,
        #[ArgumentDescription('The URL to fetch', 'https://example.com')]
        string $url,
        #[ArgumentDescription('HTML offset to start reading from', 0)]
        int $offset = 0,
        #[ArgumentDescription('Maximum length of content to read', 1048576)]
        int $length = 1048576,
    ) {
        var_dump([
            "toolName" => $toolName,
            "url" => $url,
            "offset" => $offset,
            "length" => $length
        ]);
        $ret = [];
        // Setup cache key and TTL (time-to-live in seconds)
        $cacheKey = "fetch_url:" . $url;
        $cacheTTL = 3600;

        // Try to retrieve cached data
        $cachedData = Cache::get($cacheKey, $cacheTTL);

        if ($cachedData !== null && isset($cachedData['full_contents'])) {
            $ret = $cachedData;
        } else {
            // Initialize cURL session to fetch URL content
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_USERAGENT      => 'aifix-bot; bot; curl/' . curl_version()['version'] . "; php/" . PHP_VERSION,
                CURLOPT_ENCODING       => '',
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_SSL_VERIFYSTATUS => 0,
            ]);

            $contents = curl_exec($ch);

            // Prepare result data for caching and error handling
            $curlErrorNo = curl_errno($ch);
            if ($curlErrorNo !== CURLE_OK) {
                $ret['curl_errno']   = $curlErrorNo;
                $ret['curl_error']   = curl_error($ch);
                $ret['curl_strerror'] = curl_strerror($curlErrorNo);
            }
            $ret['response_code'] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            // Save the fetched content in the result and cache it
            $ret['full_contents'] = $contents;
            Cache::set($cacheKey, $ret, $cacheTTL);
        }

        $ret['offset'] = $offset;
        $ret['length'] = $length;
        $ret['contents'] = substr($ret['full_contents'], $offset, $length);
        $ret['response_size'] = strlen($ret['full_contents']);
        unset($ret['full_contents']);
        return ($ret);
    }
);

$openai->addTool(
    'execute_shell_command',
    'Execute a shell command with optional timeout and stdin support',
    function (
        string $toolName,
        #[ArgumentDescription('The command to execute', 'ls -la')]
        string $command,
        #[ArgumentDescription('stdin to pass to the command', 'Hello, world!')]
        string $stdin,
        #[ArgumentDescription('Timeout in seconds', 30)]
        int $timeout = 30
    ) {
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
            return ([
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
                return (['error' => 'stream_select failed']);
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

        return ([
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
    // todo: add timeout
    function (
        string $toolName,
        #[ArgumentDescription('The Python script to execute', 'print("Hello, world!")')]
        string $script,
        #[ArgumentDescription('stdin to pass to the script', 'Hello, world!')]
        string $stdin
    ) {
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
        $command = "python3 " . quoteshellarg($tempScript);
        $process = proc_open($command, $descriptorSpec, $pipes);
        if (!is_resource($process)) {
            unlink($tempScript); // Clean up the temp file.
            return ([
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
        return ($result);
    }
);


$openai->addTool(
    'ask_user',
    'Ask the user a question and return their response',
    function (
        string $toolName,
        #[ArgumentDescription('The question to ask', 'How would you like to proceed?')]
        string $question
    ) {
        var_dump([
            "toolName" => $toolName,
            "question" => $question
        ]);
        echo $question . "\n";
        $line = readline("Your response: ");
        if ($line !== false) {
            readline_add_history($line);
        }

        return (['response' => trim($line)]);
    }
);
