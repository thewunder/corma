<?php

namespace Corma\Util\Inflector;

use Doctrine\Inflector\Rules\English\Inflectible;
use Doctrine\Inflector\Rules\English\Uninflected;
use Doctrine\Inflector\Rules\English\Rules as EnglishRules;
use Doctrine\Inflector\Rules\Pattern;
use Doctrine\Inflector\Rules\Patterns;
use Doctrine\Inflector\Rules\Ruleset;
use Doctrine\Inflector\Rules\Substitutions;
use Doctrine\Inflector\Rules\Transformations;

class Rules
{
    public static function getSingularRuleset(): Ruleset
    {
        return EnglishRules::getSingularRuleset();
    }

    public static function getPluralRuleset(): Ruleset
    {
        return new Ruleset(
            new Transformations(...Inflectible::getPlural()),
            //override, plural of children is children....
            new Patterns(new Pattern('children'), ...Uninflected::getPlural()),
            new Substitutions(...Inflectible::getIrregular())
        );
    }
}
