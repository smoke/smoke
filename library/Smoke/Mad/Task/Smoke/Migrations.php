<?php
/**
 * This is task that is used to manage application migrations (models structure, db structure, cache folders, etc.)
 * 
 * TODO: <Rado> 
 *     For now implementation will delegate execution to Smoke migrations, 
 *     but afterwards it would delegate to the default migrations package I create,
 *     or may be implement it right here
 * 
 * @author Radoslav Kirilov <https://github.com/smoke>
 * @copyright Radoslav Kirilov <https://github.com/smoke>
 */
class Smoke_Mad_Task_Smoke_Migrations extends Mad_Task_Set
{
    public static $migrationsPath = null;
    
    /**
     * @var Smoke_Migration_Migrator
     */
    protected $_migrator;
    
    /**
     * Initializes Smoke Migrator and returns it
     * @return Smoke_Migration_Migrator
     */
    protected function _smokeMigrator()
    {
        if (!$this->_migrator) {
            chdir(PUBLIC_PATH);
            
            $databaseAdapter = null;
            
            if (Zend_Db_Table_Abstract::getDefaultAdapter()) {
                $databaseAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
            } elseif ((@$GLOBALS['application']) instanceof Zend_Application) {
                $databaseOptions = $GLOBALS['application']->getOption('database');
                $databaseAdapter = $databaseOptions['adapter'];
                unset($databaseOptions['adapter']);
                
                $databaseAdapter = Zend_Db::factory($databaseAdapter, $databaseOptions);
            }
            
            $this->_migrator = new Smoke_Migration_Migrator(
                $databaseAdapter,
                self::$migrationsPath 
                    ? self::$migrationsPath
                    : ROOT_PATH . '/maintenance/migrations/deltas' /* . PATH_SEPARATOR . ROOT_PATH . '/vendor/lib/Other/migrations/deltas' */
            );
            
            $this->_migrator->createSchemaVersionsIfNotExists();
        }

        return $this->_migrator;
    }
    

    /**
     * Prints detailed usage
     */
    public function migrations()
    {
        // Original usage taken from Doctrine_Migrations
//    Available commands for the "migrations" namespace:
//        :diff      Generate a migration by comparing your current database to your mapping information.
//        :execute   Execute a single migration version up or down manually.
//        :generate  Generate a blank migration class.
//        :migrate   Execute a migration to a specified version or the latest available version.
//        :status    View the status of a set of migrations.
//        :version   Manually add and delete migration versions from the version table.
        echo <<<HEREDOC
Available commands for the "migrations" namespace:
    :execute   Execute a single migration version up or down manually, defaults to up.
               Example:
                 migrations:execute 20101214130001 [--dry-run] [--up|--down] [--force]

    :migrate   Execute a migration to a specified version (automatic up or down) or the latest available version.
               Example:
                 migrations:migrate 20101214130001 [--dry-run]

    :status    View the status of a set of migrations or given migration key.
               Example:
                 migrations:status [20101214130001]

    :version   Manually add and delete migration versions from the version table.
               Example:
                 migrations:version 20101214130001 --add|--delete

    :generate  Generate a blank migration class.
               Example:
                 migrations:generate '[20101214130001_]UniqueMigrationClassNameAsTitleHere' 

HEREDOC;

    }

    /**
     * Applies a single migration up or down, defaults as up
     */
    public function migrations_execute($migrationKey = null, $direction = 'up', $force = false, $dry_run = null)
    {
        $script  = array_shift($GLOBALS['argv']);
        $task    = array_shift($GLOBALS['argv']);
        
        if (false !== ($pos = array_search('--dry-run', $GLOBALS['argv']))) {
            $dry_run = true;
            unset($GLOBALS['argv'][$pos]);
        }
        
        if (false !== ($pos = array_search('--down', $GLOBALS['argv']))) {
            $direction = 'down';
            unset($GLOBALS['argv'][$pos]);
        }
        
        if (false !== ($pos = array_search('--force', $GLOBALS['argv']))) {
            $force = true;
            unset($GLOBALS['argv'][$pos]);
        }
        
        $GLOBALS['argv'] = array_values($GLOBALS['argv']);
        
        $migrationKey = preg_replace('/(\d+)/', '$1', array_shift($GLOBALS['argv']));
        $migrationKey = strlen($migrationKey) ? $migrationKey : null;
        
        if (is_null($migrationKey)) {
            echo 'There is no migration key given!!!' . PHP_EOL . PHP_EOL;
            // Print usage
            $this->migrations();
            return;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $availableMigrations = $migrator->getAvailableMigrations($migrator->migrationsPath);
        
        if (!isset($availableMigrations[$migrationKey])) {
            echo 'There is no migration at all for the given key '.$migrationKey.'!!!' . PHP_EOL;
            // Print usage
            $this->migrations();
            return;
        }
        
        $migrationFilepath = $availableMigrations[$migrationKey];
        
        if (!$force) {
            // If not forced check if that migration action is already done and skip if so
            $installedMigrations = $migrator->getInstalledMigrations();
            $migrationIsApplyed = in_array($migrationKey, $installedMigrations);
            if ('up' == $direction && $migrationIsApplyed) {
                echo 'There is migration applyed for the key given - skipping up for '.$migrationKey.', may be use --force !!!' . PHP_EOL . PHP_EOL;
                // Print usage
                $this->migrations();
                return;
            } elseif ('down' == $direction && !$migrationIsApplyed) {
                echo 'There is NO migration applyed for the key given - skipping down for '.$migrationKey.', may be use --force !!!' . PHP_EOL . PHP_EOL;
                // Print usage
                $this->migrations();
                return;
            }
        }
        
        if (!$dry_run) {
            Smoke_Migration_Base::run($migrationFilepath, $direction, $migrator->verbose, $migrator->db);
        } else {
            echo 'Would '.($direction == 'up' ? 'migrate' : 'revert').' ' . $migrationFilepath . PHP_EOL;
        }
    }
    
    /**
     * Applies migrations up or down to given version, defaults as up to last version
     */
    public function migrations_migrate($version = null, $dry_run = null)
    {
        $script  = array_shift($GLOBALS['argv']);
        $task    = array_shift($GLOBALS['argv']);
        
        if (false !== ($pos = array_search('--dry-run', $GLOBALS['argv']))) {
            $dry_run = true;
            unset($GLOBALS['argv'][$pos]);
        }
        
        $GLOBALS['argv'] = array_values($GLOBALS['argv']);
        
        $version = preg_replace('/.*?(\d+).*/', '$1', array_shift($GLOBALS['argv']));
        if (!is_numeric($version)) {
            $version = null;
        }
        
        $migrator = $this->_smokeMigrator();
        $migrator->migrate($version, $dry_run);
    }
    
    /**
     * Shows detailed migrations status
     */
    public function migrations_status($migrationKey = null)
    {
        $script  = array_shift($GLOBALS['argv']);
        $task    = array_shift($GLOBALS['argv']);
        
        $migrationKey = preg_replace('/.*?(\d+).*/', '$1', array_shift($GLOBALS['argv']));
        if (!is_numeric($migrationKey)) {
            $migrationKey = null;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $availableMigrations = $migrator->getAvailableMigrations($migrator->migrationsPath);
        $installedMigrations = $migrator->getInstalledMigrations();
                
        if (!$migrationKey) {
            echo 'Listing statuses for all migrations:' . PHP_EOL;
            foreach ($availableMigrations as $migrationKey => $migrationFilepath) {
                $status = in_array($migrationKey, $installedMigrations)?('migrated'):('not-migrated');
                printf("\t%14s: %12s : %s\n", $migrationKey, $status, $migrationFilepath);
            }
        } else {
            echo "Listing status for migration key {$migrationKey}:" . PHP_EOL;
            $migrationFilepath = @$availableMigrations[$migrationKey];
            if ($migrationFilepath) {
                $status = in_array($migrationKey, $installedMigrations)?('migrated'):('not-migrated');
            } else {
                $status = 'no-migration';
            }
            printf("\t%14s: %12s\n", $migrationKey, $status, $migrationFilepath);
        }
    }
    
    /**
     * Manually add and delete migration versions from the version table.
     */
    public function migrations_version($migrationKey = null)
    {
        $script  = array_shift($GLOBALS['argv']);
        $task    = array_shift($GLOBALS['argv']);
        
        $migrationKey = preg_replace('/.*?(\d+).*/', '$1', array_shift($GLOBALS['argv']));
        if (!is_numeric($migrationKey)) {
            $migrationKey = null;
        }
                
        $op = array_shift($GLOBALS['argv']);
        
        if (!$migrationKey) {
            echo 'You must specify migration key !!!' . PHP_EOL . PHP_EOL;
            $this->migrations();
            return;
        }
        
        if (!in_array($op, array('--add', '--delete'), true)) {
            echo 'You must specify operation --add or --delete !!!' . PHP_EOL . PHP_EOL;
            $this->migrations();
            return;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $migration = new Smoke_Migration_Base($migrationKey, '', $migrator->verbose, $migrator->db);
        
        $migration->updateInstalledVersions($migrationKey, $op == '--add' ? 'up' : 'down');
    }
    
    /**
     * Generate a blank migration class in the first migrations path.
     */
    public function migrations_generate()
    {
        $script  = array_shift($GLOBALS['argv']);
        $task    = array_shift($GLOBALS['argv']);

        $migrationName = array_shift($GLOBALS['argv']);
        $migrationKey = null;
        if (preg_match('/^(\d{14})_([a-z]\w*)$/i', $migrationName, $matches)) {
            $migrationKey = $matches[1];
            $migrationName = $matches[2];
        } elseif (preg_match('/^[a-z]\w*$/i', $migrationName)) {
            $migrationKey = date('YmdHi01');
        } else {
            echo 'You must specify a migration to crate' . PHP_EOL . PHP_EOL;
            $this->migrations();
        }
        
        $appMigrationPath = current(explode(PATH_SEPARATOR, $this->_smokeMigrator()->migrationsPath));
        
        $migrationContents = <<<HEREDOC
<?php
class {$migrationName} extends Smoke_Migration_Base
{
    public function up()
    {
        \$this->say('This is only an example migrate up!!!');
        \$this->say('Done', true);
    }
    
    public function down()
    {
        \$this->say('This is only an example migrate down!!!');
        \$this->say('Done', true);
    }
}        
HEREDOC;
        
        $migrationFilepath = $appMigrationPath . DIRECTORY_SEPARATOR . $migrationKey . '_' . $migrationName . '.php';
        
        $success = file_put_contents($migrationFilepath,$migrationContents) ? '' : 'NOT ';
        
        echo "An empty migration was {$success}generated for {$migrationKey} in {$migrationFilepath}" . PHP_EOL;
    }
}