<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

use Pterodactyl\BlueprintFramework\Extensions\{identifier}\I18n;

/**
 * Fehler beim Aufruf eines KI-Anbieters. $kind steuert die Meldung für den Nutzer,
 * die Nachricht der Exception geht nur ins Log (ohne Schlüssel).
 */
class ProviderException extends \RuntimeException
{
    public const AUTH = 'auth';
    public const RATE = 'rate';
    public const BAD_REQUEST = 'bad_request';
    public const UNAVAILABLE = 'unavailable';
    public const TIMEOUT = 'timeout';
    public const NETWORK = 'network';
    public const BLOCKED = 'blocked';
    public const ADDRESS = 'address';

    public function __construct(public readonly string $kind, string $detail = '', public readonly int $status = 0)
    {
        parent::__construct($detail);
    }

    /** Meldung für Nutzer: sagt, was los ist, ohne Interna des Anbieters. */
    public function userMessage(): string
    {
        $known = [self::AUTH, self::RATE, self::BAD_REQUEST, self::TIMEOUT, self::BLOCKED, self::ADDRESS];

        return I18n::t('assistant.provider_' . (in_array($this->kind, $known, true) ? $this->kind : 'unavailable'));
    }

    public static function fromStatus(int $status, string $detail): self
    {
        $kind = match (true) {
            $status === 401, $status === 403 => self::AUTH,
            $status === 402, $status === 429 => self::RATE,
            $status === 408, $status === 504 => self::TIMEOUT,
            $status >= 500 => self::UNAVAILABLE,
            $status >= 300 && $status < 400 => self::UNAVAILABLE,
            default => self::BAD_REQUEST,
        };

        return new self($kind, $detail, $status);
    }
}
