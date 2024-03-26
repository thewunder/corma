<?php

namespace Corma\Exception;

/**
 * Thrown when an operation requires a primary key, but none is defined on the table
 */
final class MissingPrimaryKeyException extends CormaException
{

}
