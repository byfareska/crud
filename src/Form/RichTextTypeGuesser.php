<?php

declare(strict_types=1);

namespace Byfareska\Crud\Form;

use Byfareska\Crud\Attribute\CrudRichText;
use ReflectionProperty;
use Symfony\Component\Form\FormTypeGuesserInterface;
use Symfony\Component\Form\Guess\Guess;
use Symfony\Component\Form\Guess\TypeGuess;
use Symfony\Component\Form\Guess\ValueGuess;

/**
 * A property marked #[CrudRichText] is a field of rich text, in a form whose fields are guessed: before the guess of
 * Doctrine (a textarea of a text column).
 */
final readonly class RichTextTypeGuesser implements FormTypeGuesserInterface
{
    public function guessType(string $class, string $property): ?TypeGuess
    {
        if (!class_exists($class) || !property_exists($class, $property)) {
            return null;
        }
        $attribute = new ReflectionProperty($class, $property)->getAttributes(CrudRichText::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }
        $richText = $attribute->newInstance();
        $options = ['editor_options' => $richText->editorOptions];
        if ($richText->editor !== null) {
            $options['editor'] = $richText->editor;
        }
        if ($richText->files !== null) {
            $options['files'] = $richText->files;
        }

        return new TypeGuess(CrudRichTextType::class, $richText->options + $options, Guess::VERY_HIGH_CONFIDENCE);
    }

    public function guessRequired(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessMaxLength(string $class, string $property): ?ValueGuess
    {
        return null;
    }

    public function guessPattern(string $class, string $property): ?ValueGuess
    {
        return null;
    }
}
