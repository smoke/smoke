<?php
/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * @package   Smoke_Migration
 * @author    Radoslav Kirilov <https://github.com/smoke>
 * @copyright Radoslav Kirilov <https://github.com/smoke>
 */

/**
 * Database migrations based on ideas from Ruby on Rails and Google Gears.
 *
 * This class contains the migrator interface used to perform the database
 * migrations. Use it like this:
 *
 *     <pre>
 *     $migration = new Smoke_Migration_Migrator();
 *     $migration->migrate();  // Migrate to most recent version
 *     $migration->migrate('20080801000000');  // Migrate to a specific version
 *     $migration->migrate(0);  // Drop all tables and start afresh
 *     </pre>
 *
 * The code for actual migrations inherit from Smoke_Migration_Base
 *
 * @package   Smoke_Migration
 * @author    Radoslav Kirilov <https://github.com/smoke>
 * @copyright Radoslav Kirilov <https://github.com/smoke>
 */
class Smoke_Migration_Migrator
{
    public $verbose = true;
    private $hiddenTables = array('_schema_versions');
    public $migrationsPath;

    /**
     * A database adapter
     * @var Zend_Db_Adapter_Abstract
     */
    public $db;
    
    private $throwExceptions = false;
    
    /**
     * Constructs a migrator out of db adapter and a migration path
     * @param mixed $db Either an Adapter object, or a string naming a Registry key
     * @param string $migrationsPath
     */
    public function __construct($db = null, $migrationsPath = 'migrations', $throwExceptions = false)
    {
        $this->setDbAdapter($db);
        if (!is_null($migrationsPath)) {
            $this->setMigrationsPath($migrationsPath);
        }
        $this->throwExceptions = $throwExceptions;
    }
    
    /**
     * Set to display verbose status information when migrating.
     *
     * @param bool $verbose True if we display status information, false if not
     */
    public function setVerbose($verbose = true)
    {
        $this->verbose = $verbose;
    }
    
    /**
     * @param  mixed $db Either an Adapter object, or a string naming a Registry key
     * @return Zend_Db_Adapter_Abstract
     * @throws Zend_Db_Table_Exception
     */
    public function setDbAdapter($db = null)
    {
        $this->db = self::_setupAdapter($db);
    }
    
    public function setMigrationsPath($migrationsPath = 'migrations')
    {
        $this->migrationsPath = $migrationsPath;
    }
    
    /**
     * @param  mixed $db Either an Adapter object, or a string naming a Registry key
     * @return Zend_Db_Adapter_Abstract
     * @throws Zend_Db_Table_Exception
     */
    public static function _setupAdapter($db)
    {
        if ($db === null) {
            $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        }
        if (is_string($db)) {
            require_once 'Zend/Registry.php';
            $db = Zend_Registry::get($db);
        }
        if (!($db instanceof Zend_Db_Adapter_Abstract)) {
            require_once 'Zend/Db/Table/Exception.php';
            throw new Zend_Db_Table_Exception(
                'Argument must be of type Zend_Db_Adapter_Abstract, or a Registry key where a Zend_Db_Adapter_Abstract object is stored');
        }
        return $db;
    }
    
    /**
     * Migrate the database to a given version.
     *
     * The version argument can be one of
     * - null (default): Migrate to the most recent version
     * - string: Migrate to a specific version
     * - 0: Drop all tables and start afresh
     *
     * Try to catch errors inside a transaction, but note that CREATE TABLE
     * et al can not be rolled back.
     *
     * @param mixed $goToVersion null, 0, or a version string
     */
    public function migrate($goToVersion = null, $showOnly = true)
    {
        if ($goToVersion === 0) {
            Smoke_Migration_Base::run('', 'drop', $this->verbose, $this->db);
            return;
        }
        
        $didMigrate = false;
        
        $this->createSchemaVersionsIfNotExists();
        
        $availableMigrations = $this->getAvailableMigrations($this->migrationsPath);
        $installedMigrations = $this->getInstalledMigrations();
        if ($this->verbose) {
            foreach ($this->getMissingAppliedMigrations() as $missingMigration) {
                echo "Warning missing already applied migration {$missingMigration}\n";
            }
        }
        try {
            $this->db->beginTransaction();
            if ($goToVersion !== null) {
                // Any down migrations should be executed in reverse order
                krsort($availableMigrations);
                foreach ($availableMigrations as $version => $migration) {
                    if ($version <= $goToVersion)
                        break;
                    if (!in_array($version, $installedMigrations))
                        continue;
                    if (!$showOnly) {
                        Smoke_Migration_Base::run($migration, 'down', $this->verbose, $this->db);
                        $didMigrate = true;
                    } else {
                        echo 'Would revert ' . $migration . PHP_EOL;
                    }
                }
            }
            
            ksort($availableMigrations);
            foreach ($availableMigrations as $version => $migration) {
                if ($goToVersion !== null && $version > $goToVersion)
                    break;
                if (in_array($version, $installedMigrations))
                    continue;
                if (!$showOnly) {
                    Smoke_Migration_Base::run($migration, 'up', $this->verbose, $this->db);
                    $didMigrate = true;
                } else {
                    echo 'Would migrate ' . $migration . PHP_EOL;
                }
            }
            $this->db->commit();
        } catch (Smoke_Migration_Exception $e) {
            Smoke_Migration_Base::abortMessage(array('db' => $this->db, 'verbose' => $this->verbose));
            $didMigrate = false;
            $this->db->rollback();
            
            if ($this->throwExceptions) {
                throw $e;
            }
        }
    }
    
    /**
     * Get a list of migrations that have not yet been installed.
     *
     * @return array Migrations
     */
    public function getMissingMigrations()
    {
        $this->createSchemaVersionsIfNotExists();
        
        $availableMigrations = $this->getAvailableMigrations($this->migrationsPath);
        $installedMigrations = $this->getInstalledMigrations(true);
        
        $missingMigrations = array_diff_key($availableMigrations, $installedMigrations);
        array_walk($missingMigrations, create_function('&$v', '$v = basename($v, ".php");'));
        ksort($missingMigrations);
        
        return $missingMigrations;
    }
    
    /**
     * Get a list of migrations that have been applied but their migration file is missing in the deltas (e.g. migration script have been deleted or sth.)
     *
     * @return array of migration versions
     */
    public function getMissingAppliedMigrations()
    {
        $this->createSchemaVersionsIfNotExists();
        
        $availableMigrations = $this->getAvailableMigrations($this->migrationsPath);
        $installedMigrations = $this->getInstalledMigrations();
        return array_diff($installedMigrations, array_keys($availableMigrations));
    }
    
    // Migrator helpers ====================================================
    
    /**
     * Return an array with paths to all available migrations.
     *
     * @param  string  $path Folder where the migration files are found
     * @return array   Version => Full path
     * @access private
     */
    public function getAvailableMigrations($path)
    {
        $migrations = array();
        foreach (explode(PATH_SEPARATOR, $path) as $migrationsPath) {
            foreach (new DirectoryIterator($migrationsPath) as $file) {
                if (preg_match('/^(\d{14})_(.+)\.php$/', $file->getFilename(), $matches)) {
                    if (isset($migrations[$matches[1]])) {
                        if ($this->verbose) {
                            echo 'Overriden migration ' . $file->getPathname() . PHP_EOL;
                        }
                        continue;
                    }
                    $migrations[$matches[1]] = $file->getPathname();
                }
            }
        }
        ksort($migrations);
        return $migrations;
    }
    
    /**
     * Return an array with the version numbers for all installed migrations.
     *
     * @param  bool    $flipResultArray Flip values and keys in the return value
     * @return array
     * @access private
     */
    public function getInstalledMigrations($flipResultArray = false)
    {
        $ret = $this->db->fetchCol('SELECT version FROM _schema_versions ORDER BY version ASC ');
        
        return $flipResultArray ? array_flip($ret) : $ret;
    }
    
    /**
     * Create the schema versions table if it does not already exist.
     *
     * @access private
     */
    public function createSchemaVersionsIfNotExists()
    {
        try {
            $this->db->describeTable('_schema_versions');
        } catch (Exception $e) {
            $this->db->getConnection()->query('CREATE TABLE _schema_versions (version CHAR(14) NOT NULL PRIMARY KEY)');
        }
    }
}
