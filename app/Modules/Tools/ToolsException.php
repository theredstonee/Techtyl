<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Tools;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Error of the server tools with a message the user may read. Internal details belong in the
 * log, never in the message. The panel's handler renders status and message as JSON
 * ({"errors":[{"code","status","detail"}]}).
 *
 * Every constructor takes a key from lang/tools.php (without the "tools." prefix) and its
 * placeholders; the message is translated for the current user. Key and placeholders stay
 * available so that a background task can store them and translate later for the reader.
 */
class ToolsException extends HttpException
{
    public ?string $key = null;

    /** @var array<string, string|int|float> */
    public array $params = [];

    public static function invalid(string $key, array $params = []): self
    {
        return self::make(422, $key, $params);
    }

    public static function notFound(string $key = 'not_found', array $params = []): self
    {
        return self::make(404, $key, $params);
    }

    public static function conflict(string $key, array $params = []): self
    {
        return self::make(409, $key, $params);
    }

    public static function forbidden(string $key, array $params = []): self
    {
        return self::make(403, $key, $params);
    }

    /** An external service does not answer or answers with an error. */
    public static function upstream(string $service): self
    {
        return self::make(502, 'upstream', ['service' => $service]);
    }

    /** Rate limit of an external service reached. */
    public static function busy(string $service, int $seconds): self
    {
        $seconds = max(1, min(600, $seconds));

        return self::make(429, 'busy', ['service' => $service, 'seconds' => $seconds], ['Retry-After' => (string) $seconds]);
    }

    /** The node (Wings) does not answer. */
    public static function daemon(): self
    {
        return self::make(502, 'daemon', []);
    }

    /** Our own limit, with a Retry-After header. $key is a lang key such as share_limited. */
    public static function limited(string $key, int $seconds): self
    {
        $seconds = max(1, min(3600, $seconds));

        return self::make(429, $key, ['seconds' => $seconds], ['Retry-After' => (string) $seconds]);
    }

    /** Unexpected failure. The message stays generic; details belong in the log. */
    public static function failed(): self
    {
        return self::make(500, 'failed', []);
    }

    private static function make(int $status, string $key, array $params, array $headers = []): self
    {
        $exception = new self($status, I18n::t('tools.' . $key, $params), null, $headers);
        $exception->key = $key;
        $exception->params = $params;

        return $exception;
    }
}
