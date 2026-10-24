<?php

declare(strict_types=1);

namespace Ameax\FilterCore\Exceptions;

use RuntimeException;

/**
 * A match mode the current database cannot express, e.g. regex on SQL Server.
 */
final class UnsupportedMatchModeException extends RuntimeException {}
