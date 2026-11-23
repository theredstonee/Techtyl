<?php

namespace Pterodactyl\BlueprintFramework\Extensions\{identifier}\Overview;

/**
 * Schnittstelle fuer Kacheln, die ein Techtyl-Modul auf der Admin-Uebersicht (/admin) zeigen moechte.
 *
 * So liefert ein Modul eine Kachel: im Modulordner eine Klasse OverviewWidget anlegen, z. B.
 *   app/Modules/Tickets/OverviewWidget.php
 *   namespace Pterodactyl\BlueprintFramework\Extensions\<identifier>\Modules\Tickets;
 *   class OverviewWidget implements \Pterodactyl\BlueprintFramework\Extensions\<identifier>\Overview\Widget
 * Die Uebersicht findet die Klasse selbst (siehe Widgets::collect()); eine Registrierung an anderer
 * Stelle ist nicht noetig. Die Kachel erscheint nur, wenn das Modul eingeschaltet ist.
 *
 * Regeln:
 *  - tile() wird hoechstens einmal je 60 Sekunden aufgerufen (Ergebnis liegt im Panel-Cache).
 *  - Nur eigene Tabellen lesen, wenige aggregierte Abfragen, keine externen Aufrufe (Stripe, Wings ...).
 *  - null zurueckgeben, wenn gerade nichts Sinnvolles zu zeigen ist (dann entfaellt die Kachel).
 *  - Ausnahmen werden abgefangen und geloggt; die Kachel entfaellt dann.
 *  - Die Klasse wird ueber den Container erzeugt (app()), Konstruktor-Injektion ist also moeglich.
 *  - Texte (Titel, Zusatz) ueber I18n::t('<bereich>.<schluessel>') liefern, Zahlen mit
 *    Overview::number() formatieren; der Cache haelt je Sprache eine eigene Kachel.
 */
interface Widget
{
    public function tile(): ?Tile;
}
