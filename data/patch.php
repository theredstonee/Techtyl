<?php

/*
 * Techtyl: tauscht bei der Installation gezielt Bausteine des Panels gegen eigene aus.
 *
 *   php patch.php apply     Originale sichern, Ersetzungen aus patches.json anwenden
 *   php patch.php restore   alle gesicherten Originale zurueckschreiben
 *   php patch.php status    Zustand jeder Ersetzung ausgeben
 *
 * Arbeitsverzeichnis ist der Panel-Ordner. Jede Ersetzung ist ein exakter Textaustausch
 * (kein regulaerer Ausdruck). Findet sich der Suchtext nicht genau einmal, bleibt die Datei
 * unveraendert und das Panel behaelt an dieser Stelle sein normales Aussehen.
 *
 * Sicherungen liegen in .blueprint/extensions/techtyl/private/.backup/<pfad>. Wurde eine Datei
 * seit dem letzten Lauf von aussen veraendert (Panel- oder Blueprint-Update), wird sie als neues
 * Original gesichert, statt sie mit einer veralteten Sicherung zu ueberschreiben.
 */

$mode = $argv[1] ?? 'status';
$panel = getcwd();
$private = $panel . '/.blueprint/extensions/techtyl/private';
$backupRoot = $private . '/.backup';
$stateFile = $backupRoot . '/state.json';
$listFile = __DIR__ . '/patches.json';

function techtyl_out(string $line): void
{
    fwrite(STDOUT, 'techtyl patch: ' . $line . PHP_EOL);
}

function techtyl_safe_path(string $relative): ?string
{
    // Nur Dateien unterhalb von resources/ oder app/ des Panels, ohne Ausbruch aus dem Ordner.
    if (!preg_match('#^(resources|app)/[A-Za-z0-9_./-]+$#', $relative) || str_contains($relative, '..')) {
        return null;
    }

    return $relative;
}

function techtyl_write(string $path, string $content): bool
{
    $tmp = $path . '.techtyl-tmp';
    if (file_put_contents($tmp, $content) === false) {
        return false;
    }

    return rename($tmp, $path);
}

$state = is_file($stateFile) ? (json_decode((string) file_get_contents($stateFile), true) ?: []) : [];

if ($mode === 'restore') {
    $restored = 0;
    foreach ($state as $relative => $info) {
        $backup = $backupRoot . '/' . $relative;
        $target = $panel . '/' . $relative;
        if (techtyl_safe_path($relative) === null || !is_file($backup)) {
            continue;
        }
        // Nur zurueckschreiben, wenn die Datei noch unseren Stand hat; sonst wurde sie inzwischen
        // von aussen ersetzt und ist bereits wieder ein Original.
        if (is_file($target) && hash_file('sha256', $target) !== ($info['patched'] ?? '')) {
            techtyl_out("uebersprungen (seitdem veraendert): $relative");
            continue;
        }
        if (techtyl_write($target, (string) file_get_contents($backup))) {
            $restored++;
        } else {
            techtyl_out("FEHLER beim Zurueckschreiben: $relative");
        }
    }
    techtyl_out("$restored Datei(en) wiederhergestellt");
    exit(0);
}

// patches.json plus jede Datei in patches/*.json (je Bereich eine eigene Liste).
$files = array_merge(is_file($listFile) ? [$listFile] : [], glob(__DIR__ . '/patches/*.json') ?: []);
$list = [];
foreach ($files as $file) {
    $decoded = json_decode((string) file_get_contents($file), true);
    if (!is_array($decoded)) {
        techtyl_out(basename($file) . ' ist ungueltig, es wird nichts veraendert');
        exit(0);
    }
    foreach ($decoded as $entry) {
        $list[] = $entry;
    }
}

// Ersetzungen je Datei sammeln.
$byFile = [];
foreach ($list as $index => $patch) {
    $relative = is_array($patch) ? techtyl_safe_path((string) ($patch['file'] ?? '')) : null;
    if ($relative === null || !is_string($patch['search'] ?? null) || !is_string($patch['replace'] ?? null) || $patch['search'] === '') {
        techtyl_out("Eintrag $index ist ungueltig und wird ignoriert");
        continue;
    }
    $byFile[$relative][] = $patch;
}

$applied = 0;
$failed = 0;
foreach ($byFile as $relative => $patches) {
    $target = $panel . '/' . $relative;
    $backup = $backupRoot . '/' . $relative;
    if (!is_file($target)) {
        techtyl_out("Datei fehlt: $relative");
        $failed += count($patches);
        continue;
    }

    $current = (string) file_get_contents($target);
    $currentHash = hash('sha256', $current);
    $known = $state[$relative] ?? null;

    // Original bestimmen: vorhandene Sicherung, solange die Datei noch unseren letzten Stand hat.
    if ($known !== null && is_file($backup) && $currentHash === ($known['patched'] ?? null)) {
        $original = (string) file_get_contents($backup);
    } else {
        $original = $current;
        if (!is_dir(dirname($backup)) && !mkdir(dirname($backup), 0750, true)) {
            techtyl_out("Sicherung nicht moeglich: $relative");
            $failed += count($patches);
            continue;
        }
        file_put_contents($backup, $original);
    }

    $result = $original;
    foreach ($patches as $patch) {
        $count = substr_count($result, $patch['search']);
        if ($count !== 1) {
            techtyl_out(($count === 0 ? 'Suchtext nicht gefunden' : "Suchtext $count-mal gefunden") . " in $relative" . (isset($patch['note']) ? ' (' . $patch['note'] . ')' : ''));
            $failed++;
            continue;
        }
        $result = str_replace($patch['search'], $patch['replace'], $result);
        $applied++;
    }

    if ($mode === 'apply') {
        if ($result !== $current && !techtyl_write($target, $result)) {
            techtyl_out("FEHLER beim Schreiben: $relative");
            continue;
        }
        $state[$relative] = ['original' => hash('sha256', $original), 'patched' => hash('sha256', $result)];
    }
}

// Dateien, die nicht mehr in der Liste stehen, auf das Original zuruecksetzen.
if ($mode === 'apply') {
    foreach (array_keys($state) as $relative) {
        if (isset($byFile[$relative])) {
            continue;
        }
        $target = $panel . '/' . $relative;
        $backup = $backupRoot . '/' . $relative;
        if (is_file($backup) && is_file($target) && hash_file('sha256', $target) === ($state[$relative]['patched'] ?? '')) {
            techtyl_write($target, (string) file_get_contents($backup));
        }
        unset($state[$relative]);
    }
    if (!is_dir($backupRoot)) {
        mkdir($backupRoot, 0750, true);
    }
    file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
}

techtyl_out("$applied Ersetzung(en) " . ($mode === 'apply' ? 'angewendet' : 'anwendbar') . ", $failed nicht moeglich");
exit(0);
