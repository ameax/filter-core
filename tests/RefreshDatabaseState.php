<?php

namespace Ameax\FilterCore\Tests;

class RefreshDatabaseState
{
    public static bool $migrated = false;

    /** @var array<string>|null */
    public static ?array $tables = null;
}
