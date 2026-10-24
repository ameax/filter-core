<?php

namespace Ameax\FilterCore\Tests;

use Ameax\FilterCore\FilterCoreServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Ameax\\FilterCore\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        $this->setUpDatabase();
    }

    protected function getPackageProviders($app)
    {
        return [
            FilterCoreServiceProvider::class,
        ];
    }

    /**
     * Database driver for the test run: mysql (default), mariadb, sqlsrv or sqlite.
     *
     * Select it with DB_CONNECTION, e.g. `DB_CONNECTION=sqlsrv vendor/bin/pest`.
     */
    protected static function driver(): string
    {
        return (string) env('DB_CONNECTION', 'mysql');
    }

    public function getEnvironmentSetUp($app)
    {
        $driver = static::driver();

        $connection = match ($driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => env('DB_DATABASE', ':memory:'),
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
            'sqlsrv' => [
                'driver' => 'sqlsrv',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '1433'),
                'database' => env('DB_DATABASE', 'filter_core_testing'),
                'username' => env('DB_USERNAME', 'sa'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8',
                'prefix' => '',
                'trust_server_certificate' => true,
            ],
            default => [
                'driver' => $driver === 'mariadb' ? 'mariadb' : 'mysql',
                'host' => env('DB_HOST', '127.0.0.1'),
                'port' => env('DB_PORT', '3306'),
                'database' => env('DB_DATABASE', 'filter_core_testing'),
                'username' => env('DB_USERNAME', 'root'),
                'password' => env('DB_PASSWORD', ''),
                'charset' => 'utf8mb4',
                'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '',
                'strict' => true,
                'engine' => null,
            ],
        };

        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', $connection);
    }

    protected function setUpDatabase(): void
    {
        // Allow skipping migrations via MIGRATED=true env variable
        $isMigrated = (bool) env('MIGRATED');

        if (! RefreshDatabaseState::$migrated) {
            RefreshDatabaseState::$migrated = $isMigrated;
        }

        if (! RefreshDatabaseState::$migrated) {
            $this->dropAllTables();
            $this->runMigrations();
            RefreshDatabaseState::$tables = null;
            RefreshDatabaseState::$migrated = true;
        } else {
            $this->clearDatabaseData();
        }
    }

    protected function runMigrations(): void
    {
        // Package migrations (when they exist)
        $packageMigrationPath = __DIR__.'/../database/migrations';
        if (is_dir($packageMigrationPath)) {
            // Support both .php and .php.stub files
            $migrationFiles = array_merge(
                glob($packageMigrationPath.'/*.php') ?: [],
                glob($packageMigrationPath.'/*.php.stub') ?: []
            );

            foreach ($migrationFiles as $migrationFile) {
                $migration = include $migrationFile;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }

        // Test migrations
        $testMigrationPath = __DIR__.'/database/migrations';
        if (is_dir($testMigrationPath)) {
            foreach (glob($testMigrationPath.'/*.php') as $migrationFile) {
                $migration = include $migrationFile;
                if (is_object($migration) && method_exists($migration, 'up')) {
                    $migration->up();
                }
            }
        }
    }

    protected function dropAllTables(): void
    {
        Schema::connection('testing')->dropAllTables();
    }

    /**
     * Empty all tables and reset their identity counters between tests.
     *
     * SQL Server refuses TRUNCATE on a table referenced by a foreign key, even with
     * constraints disabled, so it deletes children first and reseeds instead.
     */
    protected function clearDatabaseData(): void
    {
        $db = DB::connection('testing');
        $tables = array_values(array_filter($this->getAllTables(), fn (string $t): bool => $t !== 'migrations'));

        if (static::driver() === 'sqlsrv') {
            // Children before parents: kois references ponds.
            usort($tables, fn (string $a, string $b): int => ($a === 'ponds') <=> ($b === 'ponds'));
            foreach ($tables as $table) {
                $db->table($table)->delete();
                $db->statement("DBCC CHECKIDENT ('{$table}', RESEED, 0) WITH NO_INFOMSGS");
            }

            return;
        }

        if (static::driver() === 'sqlite') {
            Schema::connection('testing')->withoutForeignKeyConstraints(function () use ($db, $tables): void {
                foreach ($tables as $table) {
                    $db->table($table)->delete();
                }
            });
            $db->statement('DELETE FROM sqlite_sequence');

            return;
        }

        Schema::connection('testing')->withoutForeignKeyConstraints(function () use ($db, $tables): void {
            foreach ($tables as $table) {
                $db->table($table)->truncate();
            }
        });
    }

    /**
     * @return array<string>
     */
    protected function getAllTables(): array
    {
        // Listed once per process and with the driver's own catalog: on a server with
        // many databases the generic lookup in information_schema takes seconds.
        return RefreshDatabaseState::$tables ??= $this->listTables();
    }

    /**
     * @return array<string>
     */
    protected function listTables(): array
    {
        $db = DB::connection('testing');

        $rows = match (static::driver()) {
            'sqlsrv' => $db->select('SELECT name FROM sys.tables'),
            'sqlite' => $db->select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"),
            default => $db->select('SHOW TABLES'),
        };

        return array_map(fn (object $row): string => (string) array_values((array) $row)[0], $rows);
    }
}
