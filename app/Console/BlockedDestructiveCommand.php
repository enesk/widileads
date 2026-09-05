<?php

namespace App\Console;

use Illuminate\Console\Command;

/**
 * Platzhalter-Befehl, der einen datenvernichtenden Artisan-Befehl ersetzt.
 *
 * Der Befehl wird vom DestructiveCommandGuardServiceProvider unter dem Namen
 * des Originalbefehls registriert und bricht mit Exit-Code 1 ab, statt Daten
 * zu loeschen. Saemtliche Optionen des Originalbefehls (z.B. --force) werden
 * toleriert, damit auch "migrate:fresh --force" hier landet.
 *
 * Die Klasse liegt bewusst nicht in app/Console/Commands, damit Laravel sie
 * nicht automatisch als eigenstaendigen Befehl registriert -- sie wird immer
 * mit dem Namen des zu blockierenden Befehls instanziiert.
 */
class BlockedDestructiveCommand extends Command
{
    public function __construct(string $blockedCommand)
    {
        $this->signature = $blockedCommand;
        $this->description = __('funnel.destructive.description');

        parent::__construct();

        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $this->components->error(__('funnel.destructive.blocked', [
            'command' => $this->getName() ?? '',
        ]));

        $this->line(__('funnel.destructive.hint'));

        return self::FAILURE;
    }
}
