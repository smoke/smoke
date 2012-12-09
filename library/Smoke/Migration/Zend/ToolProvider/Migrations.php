<?php
/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * @package   Smoke_Migration
 * @author    Radoslav Kirilov <https://github.com/smoke>
 * @copyright Radoslav Kirilov <https://github.com/smoke>
 */

/** @see Zend_Tool_Project_Provider_Abstract */
require_once 'Zend/Tool/Project/Provider/Abstract.php';

/** @see Smoke_Migration_Migrator */
require_once 'Smoke/Migration/Migrator.php';

/** @see Smoke_Migration_Base */
require_once 'Smoke/Migration/Base.php';

/** @see Smoke_Migration_Exception */
require_once 'Smoke/Migration/Exception.php';

/**
 * A Zend_Tool_Project_Provider for the Smoke Migration package
 *
 * @author Radoslav Kirilov
 * @see Zend_Tool_Project_Provider_Abstract
 * @package Smoke_Migration
 * @subpackage ZendToolProvider
 */
class Smoke_Migration_Zend_ToolProvider_Migrations extends Zend_Tool_Project_Provider_Abstract
{
    /**
     * @var Smoke_Migration_Migrator
     */
    protected $_migrator;
    
    public function __construct()
    {
        //$this->bootstrapApp();
    }
    
    /**
     * Loads the application instance
     *
     * @return Zend_Application
     */
    protected function _getZendApp()
    {
        $zendApp = null;
        
        if ((@$GLOBALS['application']) instanceof Zend_Application) {
            $zendApp = $GLOBALS['application'];
        } else {
            $this->_loadProfile(self::NO_PROFILE_THROW_EXCEPTION);
            
            $bootstrapResource = $this->_loadedProfile->search('BootstrapFile');
            
            $zendApp = $bootstrapResource->getApplicationInstance();
        }
        
        /* @var $zendApp Zend_Application */
        return $zendApp;
    }
    
    /**
     * Bootstraps the db from the application
     *
     * @throws Zend_Tool_Project_Provider_Exception
     * @return void|Zend_Db_Adapter_Abstract
     */
    protected function _bootsrapDb()
    {
        $zendApp = $this->_getZendApp();
        
        try {
            $zendApp->bootstrap('db');
        } catch (Zend_Application_Exception $e) {
            throw new Zend_Tool_Project_Provider_Exception('Db resource not available, you might need to configure a DbAdapter.');
            return;
        }
        
        /* @var $db Zend_Db_Adapter_Abstract */
        $db = $zendApp->getBootstrap()->getResource('db');
        
        return $db;
    }
    /**
     * Initializes Smoke Migrator and returns it
     * @return Smoke_Migration_Migrator
     */
    protected function _smokeMigrator()
    {
        if (!$this->_migrator) {
            $config = $this->_registry->getConfig();
            
            do {
                // switch to the public path
                // TODO: The ZF2 defines that the cwd should be application root
                $public_path = null;
                switch (true) {
                    case $public_path = $config->smoke->migrations->public_path:
                        break;
                    case defined('PUBLIC_PATH') && $public_path = PUBLIC_PATH:
                        break;
                    case defined('APPLICATION_PATH') && $public_path = APPLICATION_PATH . DIRECTORY_SEPARATOR . 'public':
                        break;
                }
                
                if ($public_path) {
                    chdir($public_path);
                }
            } while(false);
            
            $databaseAdapter = $this->_bootsrapDb();
            
            if (!$databaseAdapter) {
                if (Zend_Db_Table_Abstract::getDefaultAdapter()) {
                    $databaseAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
                } elseif ($databaseOptions = $this->_getZendApp()->getOption('database')) {
                    $databaseAdapter = $databaseOptions['adapter'];
                    unset($databaseOptions['adapter']);
                    
                    $databaseAdapter = Zend_Db::factory($databaseAdapter, $databaseOptions);
                }
            }
            
            $migrationsPath = $config->smoke->migrations->path;
            
            if (!$migrationsPath) {
                $migrationsPath = array('migrations' . DIRECTORY_SEPARATOR . 'deltas');
            }
            
            if ($migrationsPath instanceof Zend_Config) {
                $migrationsPath = $migrationsPath->toArray();
            }
            
            if (is_array($migrationsPath)) {
                $migrationsPath = implode(
                    PATH_SEPARATOR,
                    // convert to absolute paths
                    array_map(
                        create_function('$path', 'return realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . $path);'),
                        $migrationsPath
                    )
                );
            }
            
            $this->_migrator = new Smoke_Migration_Migrator(
                $databaseAdapter,
                $migrationsPath
            );
            
            $this->_migrator->createSchemaVersionsIfNotExists();
        }
        
        return $this->_migrator;
    }
    
    /**
     * Prints detailed usage
     */
    public function help()
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
     execute   Execute a single migration version up or down manually, defaults to up.
               Example:
                 execute migrations 20101214130001 [up|down] [force] [dry-run]
        
     migrate   Execute a migration to a specified version (automatic up or down) or the latest available version.
               Example:
                 migrate migrations 20101214130001 [dry-run]
        
     status    View the status of a set of migrations or given migration key.
               Example:
                 status migrations [20101214130001]
        
     version   Manually add and delete migration versions from the version table.
               Example:
                 version migrations 20101214130001 add|delete
        
     generate  Generate a blank migration class.
               Example:
                 generate migrations '[20101214130001_]UniqueMigrationClassNameAsTitleHere'
        
HEREDOC;
        
    }
    
    /**
     * Applies a single migration up or down, defaults as up
     */
    public function execute($migrationKey = null, $heading = 'up', $force = false, $dryRun = null)
    {
        //        $script  = array_shift($GLOBALS['argv']);
        //        $task    = array_shift($GLOBALS['argv']);
        
        //        if (false !== ($pos = array_search('--dry-run', $GLOBALS['argv']))) {
        //            $dryRun = true;
        //            unset($GLOBALS['argv'][$pos]);
        //        }
        //
        //        if (false !== ($pos = array_search('--down', $GLOBALS['argv']))) {
        //            $direction = 'down';
        //            unset($GLOBALS['argv'][$pos]);
        //        }
        //
        //        if (false !== ($pos = array_search('--force', $GLOBALS['argv']))) {
        //            $force = true;
        //            unset($GLOBALS['argv'][$pos]);
        //        }
        
        //$GLOBALS['argv'] = array_values($GLOBALS['argv']);
        
        //$migrationKey = preg_replace('/(\d+)/', '$1', array_shift($GLOBALS['argv']));
        //$migrationKey = strlen($migrationKey) ? $migrationKey : null;
        
        if (is_null($migrationKey)) {
            echo 'There is no migration key given!!!' . PHP_EOL . PHP_EOL;
            // Print usage
            $this->help();
            return;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $availableMigrations = $migrator->getAvailableMigrations($migrator->migrationsPath);
        
        if (!isset($availableMigrations[$migrationKey])) {
            echo 'There is no migration at all for the given key ' . $migrationKey . '!!!' . PHP_EOL;
            // Print usage
            $this->help();
            return;
        }
        
        $migrationFilepath = $availableMigrations[$migrationKey];
        
        if (!$force) {
            // If not forced check if that migration action is already done and skip if so
            $installedMigrations = $migrator->getInstalledMigrations();
            $migrationIsApplyed = in_array($migrationKey, $installedMigrations);
            if ('up' == $heading && $migrationIsApplyed) {
                echo 'There is migration applyed for the key given - skipping up for ' . $migrationKey
                    . ', may be use --force !!!' . PHP_EOL . PHP_EOL;
                // Print usage
                $this->help();
                return;
            } elseif ('down' == $heading && !$migrationIsApplyed) {
                echo 'There is NO migration applyed for the key given - skipping down for ' . $migrationKey
                    . ', may be use --force !!!' . PHP_EOL . PHP_EOL;
                // Print usage
                $this->help();
                return;
            }
        }
        
        if (!$dryRun) {
            Smoke_Migration_Base::run($migrationFilepath, $heading, $migrator->verbose, $migrator->db);
        } else {
            echo 'Would ' . ($heading == 'up' ? 'migrate' : 'revert') . ' ' . $migrationFilepath . PHP_EOL;
        }
    }
    
    /**
     * Applies migrations up or down to given version, defaults as up to last version
     */
    public function migrate($version = null, $dryRun = null)
    {
        $dryRun = (bool) $dryRun;
        
        //        $script  = array_shift($GLOBALS['argv']);
        //        $task    = array_shift($GLOBALS['argv']);
        //
        //        $GLOBALS['argv'] = array_values($GLOBALS['argv']);
        
        //$version = preg_replace('/.*?(\d+).*/', '$1', $version);
        if (!is_numeric($version)) {
            $version = null;
        }
        
        $migrator = $this->_smokeMigrator();
        $migrator->migrate($version, $dryRun);
    }
    
    /**
     * Shows detailed migrations status
     */
    public function status($migrationKey = null)
    {
        //        $script  = array_shift($GLOBALS['argv']);
        //        $task    = array_shift($GLOBALS['argv']);
        
        //$migrationKey = preg_replace('/.*?(\d+).*/', '$1', array_shift($GLOBALS['argv']));
        if (!is_numeric($migrationKey)) {
            $migrationKey = null;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $availableMigrations = $migrator->getAvailableMigrations($migrator->migrationsPath);
        $installedMigrations = $migrator->getInstalledMigrations();
        
        if (!$migrationKey) {
            echo 'Listing statuses for all migrations:' . PHP_EOL;
            foreach ($availableMigrations as $migrationKey => $migrationFilepath) {
                $status = in_array($migrationKey, $installedMigrations) ? ('migrated') : ('not-migrated');
                printf("\t%14s: %12s : %s\n", $migrationKey, $status, $migrationFilepath);
            }
        } else {
            echo "Listing status for migration key {$migrationKey}:" . PHP_EOL;
            $migrationFilepath = @$availableMigrations[$migrationKey];
            if ($migrationFilepath) {
                $status = in_array($migrationKey, $installedMigrations) ? ('migrated') : ('not-migrated');
            } else {
                $status = 'no-migration';
            }
            printf("\t%14s: %12s\n", $migrationKey, $status, $migrationFilepath);
        }
    }
    
    /**
     * Manually add and delete migration versions from the version table.
     */
    public function version($migrationKey = null, $operation = null)
    {
        //        $script  = array_shift($GLOBALS['argv']);
        //        $task    = array_shift($GLOBALS['argv']);
        
        $migrationKey = preg_replace('/.*?(\d+).*/', '$1', $migrationKey);
        if (!is_numeric($migrationKey)) {
            $migrationKey = null;
        }
        
        //$op = array_shift($GLOBALS['argv']);
        
        if (!$migrationKey) {
            echo 'You must specify migration key !!!' . PHP_EOL . PHP_EOL;
            $this->help();
            return;
        }
        
        if (!in_array($operation, array('add', 'delete'), true)) {
            echo 'You must specify operation add or delete !!!' . PHP_EOL . PHP_EOL;
            $this->help();
            return;
        }
        
        $migrator = $this->_smokeMigrator();
        
        $migration = new Smoke_Migration_Base($migrationKey, '', $migrator->verbose, $migrator->db);
        
        $migration->updateInstalledVersions($migrationKey, $operation == 'add' ? 'up' : 'down');
        echo 'Done' . PHP_EOL . PHP_EOL;
    }
    
    /**
     * Generate a blank migration class in the first migrations path.
     */
    public function generate($migrationName)
    {
        //        $script  = array_shift($GLOBALS['argv']);
        //        $task    = array_shift($GLOBALS['argv']);
        
        $migrationKey = null;
        if (preg_match('/^(\d{14})_([a-z]\w*)$/i', $migrationName, $matches)) {
            $migrationKey = $matches[1];
            $migrationName = $matches[2];
        } elseif (preg_match('/^[a-z]\w*$/i', $migrationName)) {
            $migrationKey = date('YmdHi01');
        } else {
            echo 'You must specify a migration to create' . PHP_EOL . PHP_EOL;
            $this->help();
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
        
        $success = file_put_contents($migrationFilepath, $migrationContents) ? '' : 'NOT ';
        
        echo "An empty migration was {$success}generated for {$migrationKey} in {$migrationFilepath}" . PHP_EOL;
    }
}
