<?php

namespace Corma\Exception;

/**
 * Thrown when a operation requires a primary key, but none is defined on the table
 */
class MissingPrimaryKeyException extends \RuntimeException
{

}
