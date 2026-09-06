<?php

declare(strict_types=1);

namespace App\Funnel\QuestionTypes;

use App\Constants\QuestionType;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Loest einen Fragetyp zu seinem Handler auf (FB-011).
 *
 * Die Zuordnung folgt der Namenskonvention: single_choice -> SingleChoiceType im
 * Namensraum App\Funnel\QuestionTypes. Ein neuer Fragetyp braucht deshalb einen
 * Enum-Case und eine Klasse, sonst nichts -- es gibt keine Liste, die man dabei
 * zu pflegen vergessen koennte.
 */
class QuestionTypeRegistry
{
    /**
     * @var array<string, QuestionTypeHandler>
     */
    private array $handlers = [];

    public function __construct(private readonly Container $container) {}

    public function for(QuestionType $type): QuestionTypeHandler
    {
        return $this->handlers[$type->value] ??= $this->resolve($type);
    }

    public function forQuestion(QuestionDefinition $question): QuestionTypeHandler
    {
        return $this->for($question->questionType());
    }

    /**
     * Alle Fragetypen mit ihrem Handler.
     *
     * @return array<string, QuestionTypeHandler>
     */
    public function all(): array
    {
        $handlers = [];

        foreach (QuestionType::cases() as $type) {
            $handlers[$type->value] = $this->for($type);
        }

        return $handlers;
    }

    private function resolve(QuestionType $type): QuestionTypeHandler
    {
        $class = __NAMESPACE__.'\\'.Str::studly($type->value).'Type';

        if (! class_exists($class) || ! is_a($class, QuestionTypeHandler::class, true)) {
            throw new RuntimeException(sprintf(
                'Zum Fragetyp "%s" fehlt die Handler-Klasse %s.',
                $type->value,
                $class,
            ));
        }

        /** @var QuestionTypeHandler $handler */
        $handler = $this->container->make($class);

        return $handler;
    }
}
