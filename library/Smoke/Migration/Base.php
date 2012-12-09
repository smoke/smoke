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
 * This class is used to implement the migrations through the up and down
 * methods. Subclass and use these methods for your own migrations.
 *
 * @package Smoke_Migration
 */
class Smoke_Migration_Base
{
    protected $verbose;
    protected $version;
    protected $name;

    /**
     * A database adapter
     * @var Zend_Db_Adapter_Abstract
     */
    protected $db;
    
    /**
     * undocumented function
     *
     * @param string $version
     * @param string $name
     * @param bool   $verbose True if we print status text, false if not
     */
    public function __construct($version, $name, $verbose = true, $db = null)
    {
        $this->verbose = $verbose;
        if (is_string($version) && $version != '')
            $version = ' ' . $version;
        $this->version = $version;
        $this->name = $name;
        $this->db = Smoke_Migration_Migrator::_setupAdapter($db);
    }
    
    public function up()
    {
        throw new Smoke_Migration_Exception('Method up must be implemented in subclass.');
    }
    
    public function down()
    {
        throw new Smoke_Migration_Exception('Method down must be implemented in subclass.');
    }
    
    private function drop()
    {
        foreach ($this->db->listTables() as $table) {
            $this->say("Dropping {$table}");
            $this->exec('DROP TABLE ' . $this->db->quoteIdentifier($table));
        }
    }
    
    /**
     * Run the migrations in this class. The migrate function is the main
     * entry point called from the migrator code.
     *
     * @param string $direction One of 'up', 'down', or 'drop'.
     */
    public function migrate($direction)
    {
        if (!is_callable(array($this, $direction))) {
            throw new Smoke_Migration_Exception("Can not call method '{$direction}'");
        }
        
        switch ($direction) {
            case 'up':
                $this->announce('migrating');
                break;
            case 'down':
                $this->announce('reverting');
                break;
            case 'drop':
                $this->announce('dropping tables');
                break;
        }
        
        $start = microtime(true);
        call_user_func(array($this, $direction));
        $time = microtime(true) - $start;
        
        switch ($direction) {
            case 'up':
                $this->announce('migrated (%.4f s.)', $time);
                break;
            case 'down':
                $this->announce('reverted (%.4f s.)', $time);
                break;
            case 'drop':
                $this->announce('dropped tables (%.4f s.)', $time);
                break;
        }
        
        $this->write();
    }
    
    /**
     * Factory method that runs a given migration.
     *
     * @param string $fileName
     * @param string $direction
     * @param bool $verbose
     * @param mixed $db
     */
    public static function run($fileName, $direction, $verbose, $db)
    {
        // We create a fake instance here since functionality is not associated
        // with any real migration class.
        $self = new self('', __CLASS__, $verbose, $db);
        if ($direction == 'drop') {
            // We create a fake instance here since drop is not associated
            // with any real migration class.
            $self->migrate('drop');
            return;
        }
        
        $inflector = new Zend_Filter_Inflector(':class', array(':class' => 'Word_DashToCamelCase'));
        preg_match('/(\d+)_(.+)\.php/', basename($fileName), $matches);
        $version = $matches[1];
        $class = $inflector->filter(array(':class' => $matches[2]));
        
        include $fileName;
        
        if (class_exists($class, false)) {
            $migration = new $class($version, $class, $verbose, $db);
            $migration->migrate($direction);
            $migration->updateInstalledVersions($version, $direction);
        } else {
            $msg = "Class '{$class}' is not present in the migration file";
            $self->write($msg);
            throw new Smoke_Migration_Exception($msg);
        }
    }
    
    /**
     * Call this method to inform the user that the migration has been aborted.
     */
    public static function abortMessage($options)
    {
        $aborter = new self('', 'ERROR', $options['verbose'], $options['db']);
        $aborter->announce('Migration aborted and changes rolled back');
    }
    
    // Info helpers =========================================================
    
    protected function announce()
    {
        $args = func_get_args();
        $format = array_shift($args);
        $text = vsprintf($format, $args);
        
        $this->write(str_pad("=={$this->version} {$this->name}: {$text} ", 78, '='));
    }
    
    protected function say($message, $subitem = false)
    {
        $this->write(($subitem ? '   -> ' : '-- ') . $message);
    }
    
    protected function sayWithTime($message, $start, $rows = null)
    {
        $this->say($message);
        $this->say(sprintf('%.4f s.', microtime(true) - $start), true);
        if (is_int($rows) && !empty($rows))
            $this->say(sprintf('%d rows', $rows), true);
        
    }
    
    protected function write($text = '')
    {
        if ($this->verbose)
            echo "$text\n";
    }
    
    // Migration helpers ====================================================
    
    /**
     * Use for data manipulation in the migrations.
     *
     * @param  string $statement SQL statement
     * @return Zend_Db_Statement_Interface
     */
    protected function query($statement)
    {
        try {
            return $this->db->query($statement);
            
        } catch (Zend_Db_Statement_Exception $e) {
            $this->write("SQL Error ({$e->getCode()}): {$e->getMessage()}");
            $this->write("When executing query\n{$statement}");
            throw new Smoke_Migration_Exception($e->getMessage(), $e->getCode(), $e);
        }
    }
    
    /**
     * Use for data manipulation in the migrations and showing a pretty message
     *
     * @param  string $statement SQL statement
     * @return Zend_Db_Statement_Interface
     */
    protected function queryWithMessage($message, $statement)
    {
        $start = microtime(true);
        $stmt = $this->query($statement);
        $this->sayWithTime($message, $start, is_object($stmt) ? $stmt->rowCount() : null);
        
        return $stmt;
    }
    
    /**
     * Used for schema manipulation in the migrations
     *
     * @param  string $statement SQL statement
     * @return Zend_Db_Statement_Interface
     */
    protected function exec($statement)
    {
        return $this->query($statement);
    }
    
    /**
     * Used for schema manipulation in the migrations and pretty message
     *
     * @param  string $statement SQL statement
     * @return Zend_Db_Statement_Interface
     */
    protected function execWithMessage($message, $statement)
    {
        $start = microtime(true);
        $stmt = $this->exec($statement);
        $this->sayWithTime($message, $start);
        
        return $stmt;
    }
    
    /**
     * Update the schema_versions table with the given version.
     *
     * @param  string  $version
     * @param  string  $direction
     * @access private
     */
    public function updateInstalledVersions($version, $direction)
    {
        switch ($direction) {
            case 'up':
            //$this->db->insert('_schema_versions', array('version' => $version));
                $this->db->query('INSERT IGNORE INTO _schema_versions values (?)', $version);
                break;
            case 'down':
                $this->db->delete('_schema_versions', $this->db->quoteInto('version = ?', $version));
                break;
        }
    }
}
