<?php

namespace Drupal\blaetter_formatters\Plugin\views\argument_validator;

use \Drupal\views\Plugin\views\argument_validator\ArgumentValidatorPluginBase;

/**
 * SingleCharacterValidator
 *
 * Validates views arguments based on the given single characters.
 * It's supposed to be used on glossary views to force urls like
 * glossary/a but prevent glossary/abcd from beeing passed to the view to
 * avoid unwanted valid urls.
 *
 * @ViewsArgumentValidator(
 *  id = "single_character",
 *  title = @Translation("Single Character")
 * )
 */
class SingleCharacterValidator extends ArgumentValidatorPluginBase
{
    /**
     * Performs validation for a given argument.
     */
    public function validateArgument($arg)
    {
        // match exactly one character out of a-z and some special chars
        return preg_match('/^[a-z\(0-9]$/i', $arg);
    }
}
