<?php

use Essentio\Core\Application;
use Essentio\Core\Argument;
use Essentio\Core\Environment;
use Essentio\Core\Request;
use Essentio\Core\Response;
use Essentio\Core\Router;

/**
 * If no identifier is provided, returns the container instance.
 *
 * @template T of object
 * @param class-string<T>|string|null $id
 * @return ($id is class-string<T> ? T : object)
 */
function app(?string $id = null): object
{
    return $id ? Application::$container->get($id) : Application::$container;
}

/**
 * This function fetches an environment variable from the Environment instance.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function env(string $key, mixed $default = null): mixed
{
    return app(Environment::class)->get($key, $default);
}

/**
 * This function binds a service to the container using the specified identifier and factory.
 *
 * @param string $id
 * @param callable $factory
 * @return object
 */
function bind(string $id, callable $factory): object
{
    return app()->bind($id, $factory);
}

/**
 * This function retrieves a command-line argument using the specified key.
 *
 * @param int|string $key
 * @param mixed $default
 * @return string|array|null
 */
function arg(int|string $key, mixed $default = null): string|array|null
{
    return app(Argument::class)->get($key, $default);
}

/**
 * Executes the provided command handler if the current command matches the specified name.
 *
 * @param string $name
 * @param callable $handle
 * @return void
 */
function command(string $name, callable $handle): void
{
    if (Application::$isWeb) {
        return;
    }

    $argv = app(Argument::class);

    if ($argv->command !== $name) {
        return;
    }

    $result = $handle($argv);

    exit(is_int($result) ? $result : 0);
}

/**
 * Fetches a value from the current Request instance using the specified key.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function request(string $key, mixed $default = null): mixed
{
    return app(Request::class)->get($key, $default);
}

/**
 * Fetches a value from the current Request instance body using the specified key.
 *
 * @param string $key
 * @param mixed  $default
 * @return mixed
 */
function input(string $key, mixed $default = null): mixed
{
    return app(Request::class)->input($key, $default);
}

/**
 * Create a GET method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function get(string $path, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("GET", $path, $handle, $middleware);
}

/**
 * Create a POST method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function post(string $path, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("POST", $path, $handle, $middleware);
}

/**
 * Create a PUT method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function put(string $path, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("PUT", $path, $handle, $middleware);
}

/**
 * Create a PATCH method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function patch(string $path, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("PATCH", $path, $handle, $middleware);
}

/**
 * Create a DELETE method route
 *
 * @param string         $path
 * @param callable       $handle
 * @param list<callable> $middleware
 * @return void
 */
function delete(string $path, callable $handle, array $middleware = []): void
{
    if (!Application::$isWeb) {
        return;
    }

    app(Router::class)->add("DELETE", $path, $handle, $middleware);
}

/**
 * Sets flash data if a value is provided, or retrieves and removes flash data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function flash(string $key, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if ($value !== null) {
        return $_SESSION["_flash"][$key] = $value;
    }

    $val = $_SESSION["_flash"][$key] ?? null;
    unset($_SESSION["_flash"][$key]);
    return $val;
}

/**
 * Sets session data if a value is provided, or retrieves session data for the given key.
 *
 * @param string $key
 * @param mixed  $value
 * @return mixed
 */
function session(string $key, mixed $value = null): mixed
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return null;
    }

    if ($value !== null) {
        return $_SESSION[$key] = $value;
    }

    return $_SESSION[$key] ?? null;
}

/**
 * The render function supports a lightweight templating syntax inspired by Mustache.
 * It recognizes patterns delimited by double curly braces for variable interpolation and control structures.
 * For example, using `{{ variable }}` will output the HTML-escaped value of “variable”, while
 * triple braces `{{{ variable }}}` output the value unescaped.
 *
 * In addition, the engine supports block constructs:
 * - **Sections:** `{{# variable }}...{{/ variable }}`:
 *     Renders the enclosed block if the “variable” is truthy.
 *     If “variable” is an array, the block is iterated for each element.
 * - **Inverted Sections:** `{{^ variable }}...{{/ variable }}`
 *     Renders the block when “variable” evaluates to falsey.
 * - **Partials/Fallbacks:** `{{> variable }}...{{/ variable }}`
 *     Attempts to render the content associated with “variable”.
 *     If not available, it falls back to rendering the inner block.
 * - **Fragments:** `{{< variable }}...{{/ variable }}`
 *     Renders only the enclosed block if the “variable” is truthy.
 *     By default renders it as if the block was never there.
 *
 * This approach enables dynamic content insertion, conditional rendering, and looping constructs within templates in a manner reminiscent of Mustache’s syntax.
 * @param string $template
 * @param array  $data
 * @return string
 */
function render(string $template, array $data = []): string
{
    if (preg_match('/^(\/|\.\/|\.\.\/)?[\w\-\/]+\.php$/', $template) === 1) {
        $template = file_get_contents(Application::fromBase($template) ?: "");
    }

    if (preg_match_all('/{{<\s*([\w\.]+)\s*}}(.*?){{\/\s*\1\s*}}/s', $template, $extractableMatches, PREG_SET_ORDER)) {
        foreach ($extractableMatches as $match) {
            $key = $match[1];
            $block = $match[2];
            $value = value(dot($key, $data));
            if ($value) {
                return render($block, $data);
            }
        }
    }

    return preg_replace_callback_array(
        [
            '/{{<\s*([\w\.]+)\s*}}(.*?){{\/\s*\1\s*}}/s' => function ($matches) use ($data) {
                $block = $matches[2];
                return render($block, $data);
            },
            '/{{\#\s*([\w\.]+)\s*}}(.*?){{\/\s*\1\s*}}/s' => function ($matches) use ($data) {
                $block = $matches[2];
                $value = value(dot($matches[1], $data));
                $rendered = "";

                if (!$value) {
                    return "";
                }

                if (is_array($value) && array_is_list($value)) {
                    foreach ($value as $item) {
                        $context = is_array($item) ? $item : ["." => $item];
                        $rendered .= render($block, array_merge($data, $context));
                    }
                } elseif (is_array($value)) {
                    $rendered .= render($block, array_merge($data, $value));
                } else {
                    $rendered .= render($block, $data);
                }

                return $rendered;
            },
            '/{{^\s*([\w\.]+)\s*}}(.*?){{\/\s*\1\s*}}/s' => function ($matches) use ($data) {
                $block = $matches[2];
                $value = value(dot($matches[1], $data));
                if ($value) {
                    return "";
                }
                return render($block, $data);
            },
            '/{{>\s*([\w\.]+)\s*}}(.*?){{\/\s*\1\s*}}/s' => function ($matches) use ($data) {
                $fallback = $matches[2];
                $value = value(dot($matches[1], $data));

                if ($value) {
                    return render($value, $data);
                }

                return render($fallback, $data);
            },
            "/\{\{\{\s*([\w\.]+)\s*\}\}\}/" => function ($matches) use ($data) {
                return value(dot($matches[1], $data)) ?? "";
            },
            "/\{\{\s*([\w\.]+)\s*\}\}/" => function ($matches) use ($data) {
                return htmlentities(value(dot($matches[1], $data)) ?? "");
            },
        ],
        $template
    );
}

/**
 * Returns a Response instance configured to redirect to the specified URI with the given status code.
 *
 * @param string $uri
 * @param int    $status
 * @return Response
 */
function redirect(string $uri, int $status = 302): Response
{
    return (new Response())->withStatus($status)->withHeaders(["Location" => $uri]);
}

/**
 * Returns a Response instance configured to send JSON data with the specified status code.
 *
 * @param mixed $data
 * @param int   $status
 * @return Response
 */
function json(mixed $data, int $status = 200): Response
{
    return (new Response())
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "application/json"])
        ->withBody(json_encode($data));
}

/**
 * Returns a Response instance configured to send plain text with the specified status code.
 *
 * @param string $text
 * @param int    $status
 * @return Response
 */
function text(string $text, int $status = 200): Response
{
    return (new Response())
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/plain"])
        ->withBody($text);
}

/**
 * Returns a Response instance configured to render an HTML view using the provided template and data.
 *
 * @param string $template
 * @param array  $data
 * @param int    $status
 * @return Response
 */
function view(string $template, array $data = [], int $status = 200): Response
{
    return (new Response())
        ->withStatus($status)
        ->withHeaders(["Content-Type" => "text/html"])
        ->withBody(render($template, $data));
}

/**
 * This function logs a message using PHP's error_log function.
 *
 * @param string $format
 * @param mixed ...$values
 * @return void
 */
function log_cli(string $format, ...$values): void
{
    error_log(sprintf($format, ...$values));
}

/**
 * Logs a message at a given log level to a file specified in the configuration.
 *
 * @param string $level
 * @param string $message
 * @return void
 */
function logger(string $level, string $message): void
{
    $level = strtoupper($level);
    $msg = sprintf("[%s] [%s]: %s\n", date("Y-m-d H:i:s"), $level, $message);
    file_put_contents(env(sprintf("%s_LOG_FILE", $level), "app.log"), $msg, FILE_APPEND);
}

/**
 * Splits a dot-separated key and traverses the provided array or object to return
 * the corresponding value, or the default if not found.
 *
 * @param string $key
 * @param mixed  $data
 * @param mixed  $default
 * @return mixed
 */
function dot(string $key, $data, mixed $default = null): mixed
{
    $segments = explode(".", $key);
    $value = $data;

    foreach ($segments as $segment) {
        if (is_array($value) && array_key_exists($segment, $value)) {
            $value = $value[$segment];
        } elseif (is_object($value) && isset($value->$segment)) {
            $value = $value->$segment;
        } else {
            return $default;
        }
    }

    return $value;
}

/**
 * In CLI mode, the data is dumped using var_dump.
 * In a web environment, the output is wrapped in <pre> tags.
 *
 * @param mixed ...$data
 * @return void
 */
function dump(...$data): void
{
    if (!Application::$isWeb) {
        var_dump(...$data);
        return;
    }

    echo "<pre>";
    var_dump(...$data);
    echo "</pre>";
}

/**
 * This function returns a new callable that passes the result of the first callback to the next callback.
 *
 * @param callable ...$callbacks
 * @return callable
 */
function pipeline(callable ...$callbacks): callable
{
    return function ($argument) use ($callbacks) {
        foreach ($callbacks as $callback) {
            $argument = call_user_func($callback, $argument);
        }
        return $argument;
    };
}

/**
 * Retry the callable passed x amount of times with an optional sleep, if it fails all, throw the last error.
 *
 * @param int $times
 * @param callable $callback
 * @param int $sleep
 * @return mixed
 * @throws \Throwable
 */
function retry(int $times, callable $callback, int $sleep = 0): mixed
{
    beginning:
    $times--;

    try {
        return call_user_func($callback);
    } catch (Throwable $e) {
        logger("error", $e->getMessage());
        throw_if($times <= 0, $e);

        if ($sleep) {
            usleep($sleep * 1000);
        }

        goto beginning;
    }

    // shut up intelephense
    return null;
}

/**
 * This function attempts to execute the provided callable. If any exception or error is thrown,
 * it logs the error message using the error log mechanism and returns the default value.
 *
 * @param callable $callback
 * @param mixed    $default
 * @return mixed
 */
function safe(callable $callback, mixed $default = null): mixed
{
    try {
        return call_user_func($callback);
    } catch (Throwable $e) {
        logger("error", $e->getMessage());
        return $default;
    }
}

/**
 * This function allows you to perform an operation on the value and then
 * return the original value.
 *
 * @param mixed    $value
 * @param callable $callback
 * @return mixed
 */
function tap(mixed $value, callable $callback): mixed
{
    $callback($value);
    return $value;
}

/**
 * Evaluates the provided condition, and if it is true, throws the specified exception.
 *
 * @param bool $condition
 * @param Throwable $e
 * @return void
 * @throws Throwable
 */
function throw_if(bool $condition, Throwable $e): void
{
    if ($condition) {
        throw $e;
    }
}

/**
 * If the value is callable, it executes the callback and returns its result.
 * Otherwise, it returns the value as is.
 *
 * @param mixed $value
 * @return mixed
 */
function value(mixed $value): mixed
{
    if (is_callable($value)) {
        return call_user_func($value);
    }

    return $value;
}

/**
 * If the condition is false, the function returns null.
 * If the condition is true and the callback is callable, it executes the callback and returns its result;
 * otherwise, it returns the provided value directly.
 *
 * @param bool  $condition
 * @param mixed $callback
 * @return mixed
 */
function when(bool $condition, mixed $value): mixed
{
    if (!$condition) {
        return null;
    }

    return value($value);
}
