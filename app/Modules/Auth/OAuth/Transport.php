<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth\OAuth;

/**
 * Der HTTP-Teil der Anbieter-Anbindung. Die echte Umsetzung ist HttpTransport; Tests binden
 * im Container eine Attrappe an diese Schnittstelle.
 */
interface Transport
{
    /**
     * POST mit Formulardaten, erwartet JSON.
     *
     * @param array<string, string> $form
     * @return array<string, mixed>|null null bei jedem Fehler
     */
    public function postForm(string $url, array $form): ?array;

    /**
     * GET mit Bearer-Token, erwartet JSON.
     *
     * @return array<string, mixed>|null null bei jedem Fehler
     */
    public function getJson(string $url, string $bearer): ?array;
}
