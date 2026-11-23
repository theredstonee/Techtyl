<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Assistant\Providers;

/**
 * Gemeinsame Schnittstelle aller KI-Anbieter: ein Treiber je API-Familie
 * (Anthropic Messages, OpenAI-kompatible Chat Completions, Gemini, Azure, Demo).
 * Treiber haben keine Werkzeuge und fuehren nichts aus; sie liefern nur Text.
 */
interface Driver
{
    /**
     * Schickt das Gespraech an den Anbieter und reicht die Antwort stueckweise weiter.
     *
     * @param string $system Systemnachricht (nur vom Server gebaut, nie vom Nutzer)
     * @param array<int, array{role: string, content: string}> $messages abwechselnd "user" und "assistant", endet mit "user"
     * @param int $maxTokens Obergrenze fuer die Antwortlaenge
     * @param callable(string): bool $onText bekommt jedes Textstueck; false bricht den Aufruf ab
     * @param callable(): bool $onIdle wird aufgerufen, solange der Anbieter schweigt; false bricht ab
     * @throws ProviderException
     */
    public function chat(string $system, array $messages, int $maxTokens, callable $onText, callable $onIdle): Result;
}
