<?php

declare(strict_types=1);

function getUserHomeDir(): string
{
    // Provide a fallback for Windows environments where HOME may not be set
    $home = getenv('HOME');
    if (!empty($home)) {
        return $home;
    }
    $userProfile = getenv('USERPROFILE');
    if (!empty($userProfile)) {
        return $userProfile;
    }
    $drive = getenv('HOMEDRIVE');
    $path = getenv('HOMEPATH');
    if (!empty($drive) && !empty($path)) {
        return $drive . $path;
    }
    return __DIR__;
}

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    if (error_reporting() & $errno) {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }
});
//require_once(__DIR__ . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'OpenAI.class.php');
require_once(__DIR__ . DIRECTORY_SEPARATOR . 'stuff' . DIRECTORY_SEPARATOR . 'Cache.class.php');

function dd(...$args)
{
    $trace = debug_backtrace();
    $caller = array_shift($trace);
    $file = $caller['file'];
    $line = $caller['line'];
    echo "dd() called in {$file}:{$line}:" . PHP_EOL;
    var_dump(...$args);
    die();
}

function js_decode(string $json): mixed
{
    return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
}

function js_encode(mixed $data): string
{
    return json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR);
}

function return_var_dump(...$args): string
{
    ob_start();
    var_dump(...$args);
    $output = ob_get_clean();
    return $output;
}

function run_input_command(array $args): array
{
    if (0) {
        global $argv;
        $args = $argv;
        array_shift($args);
    }
    if (empty($args)) {
        throw new Exception('Usage: aifix failing thing');
    }
    $compiledCommand = '';
    foreach ($args as $arg) {
        if (str_contains($arg, "\x00")) {
            throw new Exception('Null byte in argument');
        }
        $validCharacters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ.,-_+';
        if (strspn($arg, $validCharacters) === strlen($arg)) {
            $compiledCommand .= $arg . ' ';
            continue;
        }
        $isUnix = DIRECTORY_SEPARATOR === '/';
        if ($isUnix) {
            // php's escapeshellarg for unix is broken: https://github.com/divinity76/phpquoteshellarg
            $compiledCommand .=  "'" . \strtr($arg, array("'" => "'\\''")) . "' ";
        } else {
            // i am not touching https://learn.microsoft.com/en-gb/archive/blogs/twistylittlepassagesallalike/everyone-quotes-command-line-arguments-the-wrong-way
            $compiledCommand .= escapeshellarg($arg) . ' ';
        }
    }
    $compiledCommand = rtrim($compiledCommand, ' ');
    $descriptor_spec = [
        // Child inherits parent's stdin by default.
        1 => ['pipe', 'wb'],
        2 => ['pipe', 'wb'],
    ];

    $pipes = [];
    var_dump(["compiledCommand" => $compiledCommand]);
    $proc = proc_open($compiledCommand, $descriptor_spec, $pipes);
    if (!is_resource($proc)) {
        throw new Exception(
            'Failed to start process: ' . var_export($compiledCommand, true) . ' ' .
                var_export(error_get_last(), true)
        );
    }

    // Set pipes to non-blocking mode
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    $stdout_log = '';
    $stderr_log = '';
    $std_combined_log = '';

    // Read output while the process is running
    do {
        $status = proc_get_status($proc);
        $read = [$pipes[1], $pipes[2]];
        $write = null;
        $except = null;
        $tv_sec = 0;
        $tv_usec = 100000; // 100ms timeout

        $num_changed_streams = stream_select($read, $write, $except, $tv_sec, $tv_usec);
        if ($num_changed_streams === false) {
            throw new Exception('stream_select failed');
        }

        foreach ($read as $stream) {
            // Keep reading until no more data is available on this iteration.
            while (($data = fread($stream, 8192)) !== false && $data !== '') {
                if ($stream === $pipes[1]) {
                    $stdout_log .= $data;
                    var_dump($data);
                } elseif ($stream === $pipes[2]) {
                    $stderr_log .= $data;
                    var_dump($data);
                }
                $std_combined_log .= $data;
            }
        }
        // Sleep briefly to avoid busy-waiting
        // usleep(100000);
    } while ($status['running']);

    // Drain any remaining data after the process has ended.
    foreach ([$pipes[1], $pipes[2]] as $pipe) {
        while (!feof($pipe)) {
            $data = fread($pipe, 8192);
            if ($data === false || $data === '') {
                break;
            }
            if ($pipe === $pipes[1]) {
                $stdout_log .= $data;
            } else {
                $stderr_log .= $data;
            }
            $std_combined_log .= $data;
        }
    }

    // Close pipes
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($proc);
    $exit_code = $status['exitcode'];
    return [
        "command" => $compiledCommand,
        "exit_code" => $exit_code,
        "stdout" => $stdout_log,
        "stderr" => $stderr_log
    ];
}

function get_openai_api_key(): string
{
    $home = getUserHomeDir();
    $configFile = $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'aido.json';
    if (!file_exists($configFile)) {
        throw new Exception('OpenAI api key not found at ' . $configFile);
    }
    $contents = file_get_contents($configFile);
    $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    if (empty($config['api_key'])) {
        throw new Exception('OpenAI key is required. Unable to read key from ' . $configFile);
    }
    $key = trim($config['api_key']);
    return $key;
}

function pick_ai_model(): string
{
    global $argv;
    global $argc;
    $home = getUserHomeDir();
    $configFile = $home . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'aido.json';
    $model = 'o4-mini';
    if (file_exists($configFile)) {
        $contents = file_get_contents($configFile);
        $config = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!empty($config['default_model'])) {
            $model = $config['default_model'];
        }
    }
    // Process command-line arguments for the --model option
    foreach ($argv as $index => $arg) {
        if (strpos($arg, '--model=') === 0) {
            $providedModel = substr($arg, strlen('--model='));
            $aliases = array(
                '4o' => 'gpt-4o',
                '4omini' => 'gpt-4o-mini',
                '4o-mini' => 'gpt-4o-mini',
                'o3mini' => 'o3-mini',
                'o4mini' => 'o4-mini',
            );
            $model = $aliases[$providedModel] ?? $providedModel;
            // Remove the model argument so that the remaining arguments are processed normally
            unset($argv[$index]);
        }
    }
    $argv = array_values($argv);
    $argc = count($argv);
    return $model;
}

/**
 * quotes shell arguments
 * (doing a better job than escapeshellarg)
 *
 * @param string|int|float $arg
 * @throws \UnexpectedValueException if $arg contains null bytes
 * @return string
 */

function quoteshellarg($arg): string
{
    if (\is_float($arg)) {
        // 17: >The 53-bit significand precision gives from 15 to 17 significant decimal digits precision.
        return \escapeshellarg(\sprintf('%.17g', $arg));
    }
    if (\is_int($arg)) {
        return \escapeshellarg((string) $arg);
    }
    if (!\is_string($arg)) {
        throw new \InvalidArgumentException('Argument #1 ($arg) must be of type string|int|float, ' . (is_object($arg) ? get_class($arg) : gettype($arg)) . ' given');
    }
    static $isUnix = null;
    if ($isUnix === null) {
        $isUnix = \in_array(PHP_OS_FAMILY, array('Linux', 'BSD', 'Darwin', 'Solaris'), true) || PHP_OS === 'CYGWIN';
    }
    if ($isUnix) {
        // PHP's built-in escapeshellarg() for unix is kindof garbage: https://3v4l.org/Hkv7h
        // corrupting-or-stripping UTF-8 unicode characters like "æøå" and non-printable characters like "\x01",
        // both of which are fully legal in unix shell arguments.
        // In single-quoted-unix-shell-arguments there are only 2 bytes that needs special attention: \x00 and \x27
        if (false !== \strpos($arg, "\x00")) {
            throw new \UnexpectedValueException('unix shell arguments cannot contain null bytes!');
        }
        return "'" . \strtr($arg, array("'" => "'\\''")) . "'";
    }
    // todo: quoteshellarg for windows? it's a nightmare though: https://docs.microsoft.com/en-us/archive/blogs/twistylittlepassagesallalike/everyone-quotes-command-line-arguments-the-wrong-way
    // fallback to php's builtin escapeshellarg
    return \escapeshellarg($arg);
}

if (!function_exists('readline')) {
    function readline(string $prompt = ''): string
    {
        echo $prompt . ': ';
        return rtrim(fgets(STDIN), "\n");
    }
    function readline_add_history(string $line): void
    {
        // No-op: This is a placeholder for environments without readline support.
    }
}
