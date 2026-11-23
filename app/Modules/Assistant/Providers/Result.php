<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/** Ergebnis eines Aufrufs: gesamter Text, Grund des Endes und (falls gemeldet) der Verbrauch. */
class Result
{
    public const END = 'end';
    public const LENGTH = 'length';
    public const REFUSAL = 'refusal';
    public const ABORTED = 'aborted';

    public string $text = '';
    public string $stop = self::END;
    public ?int $tokensIn = null;
    public ?int $tokensOut = null;
}
