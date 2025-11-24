<?php

namespace Ameax\FilterCore\Tests;

use Ameax\FilterCore\FilterCoreServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;
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

    public function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'mysql',
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
        ]);
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
        DB::connection('testing')->statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            DB::connection('testing')->statement("DROP TABLE IF EXISTS `{$table}`");
        }

        DB::connection('testing')->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function clearDatabaseData(): void
    {
        DB::connection('testing')->statement('SET FOREIGN_KEY_CHECKS=0');

        $tables = $this->getAllTables();

        foreach ($tables as $table) {
            if ($table !== 'migrations') {
                DB::connection('testing')->table($table)->truncate();
            }
        }

        DB::connection('testing')->statement('SET FOREIGN_KEY_CHECKS=1');
    }

    protected function getAllTables(): array
    {
        $tables = DB::connection('testing')
            ->select('SHOW TABLES');

        $dbName = env('DB_DATABASE', 'filter_core_testing');
        $key = "Tables_in_{$dbName}";

        return array_map(fn ($table) => $table->$key, $tables);
    }
}
