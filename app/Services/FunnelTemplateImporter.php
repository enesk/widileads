<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\FunnelTemplateNotFound;
use App\Models\Funnel;
use App\Models\Tenant;

/**
 * Legt aus einer Vorlage einen echten Funnel in den Live-Tabellen an (FB-019).
 *
 * Die Vorlagen liegen als JSON unter `database/templates` im Snapshot-Format
 * (siehe docs/funnel-builder/snapshot-format.md). Geschrieben wird ueber den
 * FunnelStructureWriter -- dieselbe Stelle, die auch die tiefe Kopie eines
 * Funnels schreibt (FB-018). Der Importer kuemmert sich nur darum, welche Datei
 * gelesen wird.
 *
 * Angelegt wird immer ein Entwurf. Ob und wann veroeffentlicht wird, entscheidet
 * der Betreiber.
 */
class FunnelTemplateImporter
{
    public function __construct(private readonly FunnelStructureWriter $structureWriter) {}

    /**
     * Schluessel aller Vorlagen, die unter database/templates liegen.
     *
     * @return list<string>
     */
    public function availableTemplates(): array
    {
        $files = glob($this->directory().DIRECTORY_SEPARATOR.'*.json');

        if ($files === false) {
            return [];
        }

        return array_values(array_map(
            static fn (string $path): string => basename($path, '.json'),
            $files,
        ));
    }

    /**
     * Legt die Vorlage als neuen Entwurf fuer diesen Mandanten an.
     *
     * @throws FunnelTemplateNotFound
     */
    public function import(string $templateKey, Tenant $tenant): Funnel
    {
        return $this->structureWriter->write($this->read($templateKey), $tenant);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws FunnelTemplateNotFound
     */
    private function read(string $templateKey): array
    {
        $path = $this->directory().DIRECTORY_SEPARATOR.basename($templateKey).'.json';

        if (! is_file($path)) {
            throw FunnelTemplateNotFound::forKey($templateKey);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    private function directory(): string
    {
        return database_path('templates');
    }
}
