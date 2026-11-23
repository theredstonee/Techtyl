<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Modules\Auth;

/**
 * Eine Registrierung konnte nicht abgeschlossen werden. Der Grund ist ein fester Schluessel:
 * link (Link ungueltig oder Adresse inzwischen vergeben), closed, domain, username, invite.
 */
class RegistrationFailed extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('techtyl auth: Registrierung nicht moeglich (' . $reason . ')');
    }
}
