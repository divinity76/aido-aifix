<?php

declare(strict_types=1);
function get_screenshot()
{
    // We only want to figure out our environment once, so we store it in a static variable.
    static $osDetected = null;
    if ($osDetected === null) {
        if (!empty(getenv('WSL_DISTRO_NAME'))) {
            $osDetected = 'wsl';
        } elseif (stripos(PHP_OS, 'WIN') === 0) {
            $osDetected = 'windows';
        } elseif (PHP_OS === 'Darwin') {
            $osDetected = 'macos';
        } elseif (PHP_OS === 'Linux') {
            $osDetected = 'linux';
        } else {
            throw new Exception("Unsupported OS: " . PHP_OS);
        }
    }

    switch ($osDetected) {
        case 'wsl':
        case 'windows':
            // Use png over JPG because of poor JPG compression in Powershell.
            $powerShell = <<<'PS'
Add-Type -TypeDefinition @" 
using System;
using System.Runtime.InteropServices;

public class Win32 {
    [DllImport("user32.dll")]
    public static extern IntPtr GetForegroundWindow();

    [DllImport("user32.dll")]
    public static extern bool GetWindowRect(IntPtr hWnd, out RECT rect);
}

public struct RECT {
    public int Left;
    public int Top;
    public int Right;
    public int Bottom;
}
"@;

$hwnd = [Win32]::GetForegroundWindow();
$rect = New-Object RECT;
[Win32]::GetWindowRect($hwnd, [ref]$rect) | Out-Null;

$width = $rect.Right - $rect.Left;
$height = $rect.Bottom - $rect.Top;

Add-Type -AssemblyName System.Drawing;
$bitmap = New-Object System.Drawing.Bitmap $width, $height;
$graphics = [System.Drawing.Graphics]::FromImage($bitmap);
$graphics.CopyFromScreen($rect.Left, $rect.Top, 0, 0, $bitmap.Size);

$ms = New-Object System.IO.MemoryStream;
$bitmap.Save($ms, [System.Drawing.Imaging.ImageFormat]::Png);
$ms.Position = 0;

[byte[]]$bytes = $ms.ToArray();
[System.Console]::OpenStandardOutput().Write($bytes, 0, $bytes.Length);
PS;
            $command = 'powershell.exe -ExecutionPolicy Bypass -Command ' . quoteshellarg($powerShell);
            ob_start();
            passthru($command, $return_var);
            $pngData = ob_get_clean();
            if ($return_var !== 0) {
                throw new Exception("Failed to take screenshot. Powershell return code: $return_var");
            }
            return "data:image/png;base64," . base64_encode($pngData);
        case 'macos':
            // TODO: untested.
            // macOS provides the 'screencapture' command by default.
            // The -x flag prevents the screenshot “flash”, and -t png uses PNG.
            // Write to stdout so we can capture it in PHP.
            $cmd = 'screencapture -x -t png /dev/stdout';
            ob_start();
            passthru($cmd, $return_var);
            $pngData = ob_get_clean();
            if ($return_var !== 0) {
                throw new Exception("Failed to take screenshot on macOS. Return code: $return_var");
            }
            return "data:image/png;base64," . base64_encode($pngData);

        case 'linux':
            // TODO: untested.
            // Common tools for Linux:
            //   1) `import` (from ImageMagick) captures the X-Window.
            //   2) `gnome-screenshot` can capture, but might not support stdout.
            // We'll use the ImageMagick `import` tool:
            //   -window root  => capture the entire screen
            //   png:-         => write PNG to stdout
            $cmd = 'import -window root png:-';

            ob_start();
            passthru($cmd, $return_var);
            $pngData = ob_get_clean();
            if ($return_var !== 0) {
                throw new Exception("Failed to take screenshot on Linux. Return code: $return_var");
            }
            return "data:image/png;base64," . base64_encode($pngData);
        default:
            throw new Exception("Unsupported OS environment: $osDetected - " . PHP_OS);
    }
}
$openai->addTool(
    'gui_screenshot',
    'Capture a screenshot of the current screen',
    [],
    function ($toolName) {
        $screenshot = get_screenshot();
        $returnRaw = true;
        if ($returnRaw) {
            // will return raw binary image to the model.. idk if that will actually work
            $screenshot = substr($screenshot, strpos($screenshot, ',') + 1);
            $screenshot = base64_decode($screenshot);
            return $screenshot;
        }
        $removeHeader = false;
        if ($removeHeader) {
            $screenshot = substr($screenshot, strpos($screenshot, ',') + 1);
        }
        if (0) {
            return $screenshot; // save bandwidth by not json-encoding an already base64 encoded large image.
        }
        $key_name = $removeHeader ? 'screenshot_base64' : 'screenshot_uri';
        return js_encode([$key_name => $screenshot]);
    }
);
