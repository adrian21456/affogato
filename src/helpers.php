<?php

use Doctrine\Inflector\InflectorFactory;
use GuzzleHttp\Psr7\UploadedFile;
use PHPUnit\Framework\Assert;
use Symfony\Component\HttpFoundation\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Http\File;

function getModuleName($controllerName): string
{
    // Remove "Controller" suffix from the class name
    $moduleName = str_replace('Controller', '', class_basename($controllerName));

    // Return the module name in a readable format, e.g., Category -> category
    return ucfirst(strtolower($moduleName));
}

function getActionName($action)
{
    // Return the custom name or the original action if not found in the map
    return $action;
}

function getResponseObject($result = [], $request = [], \Exception $exception = null): array
{
    // Check if the environment is local
    $isLocalEnv = env('APP_ENV') === 'local';

    $response = [
        'status' => true,
        'version' => env('APP_VERSION'),
        'timestamp' => time(),
        'timezone' => date_default_timezone_get(),
        'message' => $exception?->getMessage() ?? 'An error occurred.',
        'error' => $exception ? [
            'name'    => get_class($exception),
            'code'    => $exception->getCode(),
            'message' => $exception->getMessage(),
            'trace'   => $exception->getTraceAsString(),
        ] : null,
    ];

    if (!$exception) {
        $response['status'] = true;
        $response['message'] = 'Request successful';
        unset($response['error']);
    } else {
        unset($response['error']);
    }

    // Only include 'line' and 'file' in the error if in the local environment
    if ($isLocalEnv && !empty($exception)) {
        $response['error']['line'] = $exception->getLine();
        $response['error']['file'] = $exception->getFile();
        $response['error']['trace'] = $exception->getTrace();
    }

    // Only include 'request' if in the local environment
    if ($isLocalEnv && !empty($request)) {
        $response['request'] =  $request;
    }

    if (!empty($result)) {
        if (array_key_exists('result', $result)) {
            $response['result'] = $result['result'];
        }
        if (array_key_exists('message', $result)) {
            $response['message'] = $result['message'];
        }
        if (array_key_exists('error', $result)) {
            $response['message'] = $result['error'];
        }
    }

    if (!empty($exception)) {
        $response['status'] = false;
        abort(500, $exception->getMessage());
    }

    return $response;
}

function getFilePrefix(): string
{
    usleep(100000);
    return 'File_' . date("YmdHis") . substr((string)microtime(), 2, 3);
}

function getFileSuffix(): string
{
    return '_' . date("YmdHis") . substr((string)microtime(), 2, 3);
}

function getModPath(): string
{
    return base_path("mods");
}

function pluralize($word): string
{
    $inflector = InflectorFactory::create()->build();
    $word = explode(' ', $word);
    $last_word = end($word);
    return $inflector->pluralize($last_word);
}

function properName($name): string
{
    $name = str_replace('_', ' ', $name);
    return ucwords($name);
}

function console($context, $texts)
{
    $error_texts = ["fail", "error", "exception", "fatal", "warning", "faultcode"];
    if (!is_array($texts)) {
        $texts = [$texts];
    }
    foreach ($texts as $text) {
        foreach ($error_texts as $error_text) {
            if (str_contains(strtolower($text), $error_text)) {
                return $context->error($text);
            } else {
                return $context->comment($text);
            }
        }
    }
}

function getRoles()
{
    return json_decode(file_get_contents(base_path("core/__roles.json")), true);
}

function getColumn($config, $column)
{
    $conf = json_decode(file_get_contents(base_path("core/$config.json")), true);
    foreach ($conf['columns'] as $col) {
        if ($col['name'] === $column) {
            return $col;
        }
    }

    return null;
}

function isFileMultiple($column)
{
    return $column['frontend']['form_control'] === 'file'
        && isset($column['frontend']['controlSettings']['file']['fileMultiple'])
        && $column['frontend']['controlSettings']['file']['fileMultiple'] === true;
}

function getConfig($config)
{
    return json_decode(file_get_contents(base_path("core/$config.json")), true);
}

function makeSerializable($data)
{
    $keys = ["parameters"];

    foreach ($keys as $key) {
        if (isset($data[$key]))
            unset($data[$key]);
    }

    foreach ($data as $key => $d) {
        if ($d instanceof UploadedFile || is_object($d) || is_array($d)) {
            unset($data[$key]);
        }
    }
    return $data;
}

function removeLinesWithBrackets(string $content): string
{
    $lines = explode(PHP_EOL, $content);
    $filtered = array_filter($lines, function ($line) {
        return strpos($line, '[]') === false;
    });

    return implode(PHP_EOL, $filtered);
}

function assertDurationLessThan($start, $label, $max = null): void
{
    if (empty($max)) $max = env('TEST_MAX_DURATION', 15);
    $duration = microtime(true) - $start;

    Assert::assertLessThanOrEqual(
        $max,
        $duration,
        "{$label} exceeded {$max}s, took {$duration}s"
    );
}

function assertValidApiResponse($response, $keywords = ['error', 'fail', 'exception', 'warning']): void
{
    $json = $response->json();

    // Assert "status" exists and is true (boolean)
    Assert::assertArrayHasKey('status', $json, "Missing 'status' key in response.");
    Assert::assertTrue($json['status'], "'status' is not true.");

    // Check for error-related keywords in the entire response JSON string
    $flattened = strtolower(json_encode($json));

    foreach ($keywords as $keyword) {
        Assert::assertStringNotContainsString(
            strtolower($keyword),
            $flattened,
            "Response contains the keyword '{$keyword}'."
        );
    }
}

function handleExceptions($exceptions)
{
    $exceptions->reportable(function (\Throwable $exception) {
        Log::error('Exception occurred', [
            'exception' => [
                'name' => get_class($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'trace' => $exception->getTrace(),
            ]
        ]);
    });

    $exceptions->renderable(function (\Throwable $exception, Request $request) {
        $response = [
            'status' => false,
            'version' => env('APP_VERSION'),
            'timestamp' => time(),
            'timezone' => date_default_timezone_get(),
            'message' => $exception->getMessage(),
            'error' => [
                'name' => get_class($exception),
                'code' => $exception->getCode(),
                'message' => $exception->getMessage(),
                'line' => $exception->getLine(),
                'file' => $exception->getFile(),
                'trace' => $exception->getTrace(),
            ],
            'request' => $request->all(),
        ];

        if (env('APP_ENV') !== 'local') {
            unset($response['error']['file']);
            unset($response['error']['line']);
            unset($response['error']['trace']);
            unset($response['request']);
        }
        return response()->json($response, ($exception->getCode() === 0) ? 500 : $exception->getCode()); // You can modify the status code based on the exception type
    });
}

if (!function_exists('generateFakeFile')) {
    function generateFakeFile(string $extension, string $folder = "files"): ?string
    {
        $extension = strtolower($extension);

        // Path to your package's /faker folder
        $fakerPath = __DIR__ . '/faker';
        $fileName = "fake.{$extension}";
        $fullPath = $fakerPath . DIRECTORY_SEPARATOR . $fileName;

        $newFileName = "fake" . getFileSuffix() . "." . $extension;

        // ✅ If file exists in /faker, use it
        if (file_exists($fullPath)) {
            Log::info("Using existing file: {$fullPath}");
            return Storage::disk('public')->putFileAs($folder, new File($fullPath), $newFileName);
        }

        Log::info("File not found in /faker: {$fullPath}");

        // 🔄 Else create dummy content and save as temporary file
        $fakeContent = fake()->text(100);  // Random content (not necessarily valid for the extension)

        $tempPath = storage_path('app/public/temp_fake.' . $extension);
        file_put_contents($tempPath, $fakeContent);

        $storedPath = Storage::disk('public')->putFileAs($folder, new File($tempPath), $newFileName);

        // 🧹 Delete the temporary file after storing
        if (file_exists($tempPath)) {
            @unlink($tempPath);
        }

        return $storedPath;
    }
}

function generateDocumentParserHash(): string
{
    $hash = env('DOCUMENT_PARSER_HASH');
    $timezone = env('DOCUMENT_PARSER_TIMEZONE', 'UTC');
    $now = new \DateTime('now', new \DateTimeZone($timezone));
    $timestamp = $now->getTimestamp();

    return "{$hash}_{$timestamp}";
}

if (!function_exists('getDocumentParserURL')) {
    /**
     * Get the Document Parser URL from environment or remote configuration.
     *
     * Priority order:
     * 1. DOCUMENT_PARSER_URL environment variable (if set)
     * 2. Fetch from SOURCE_URL/urls.json (if SOURCE_URL is set)
     * 3. Fetch from https://miracodes.com/urls.json (default fallback)
     *
     * @return string|null The document parser URL or null if unavailable
     */
    function getDocumentParserURL(): ?string
    {
        // First priority: Direct environment variable
        $documentParserUrl = env('DOCUMENT_PARSER_URL');

        if (!empty($documentParserUrl)) {
            return $documentParserUrl;
        }

        // Second priority: Fetch from remote configuration
        $sourceUrl = env('SOURCE_URL');
        $urlsJsonPath = !empty($sourceUrl)
            ? rtrim($sourceUrl, '/') . '/urls.json'
            : 'https://miracodes.com/urls.json';

        try {
            $response = Http::get($urlsJsonPath);
            if ($response->successful()) {
                $urls = $response->json();
                return $urls['document_parser'] ?? null;
            }
        } catch (\Exception $e) {
            // Silently fail if remote config is unavailable
            return null;
        }

        return null;
    }
}

function extractParameters($parameters)
{
    if (isset($parameters[0]) && $parameters[0] instanceof \Illuminate\Http\Request) {
        $params = $parameters[0]->all();
        if (array_key_exists('parameters', $params)) {
            unset($params['parameters']);
        }

        return $params;
    }

    return [];
}
