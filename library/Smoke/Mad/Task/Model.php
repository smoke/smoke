<?php
/**
 * Task set related to models generation and update
 * 
 * @author Radoslav Kirilov
 * 
 */
class Smoke_Mad_Task_Model extends Mad_Task_Set
{
    /**
     * @var string classname for needed model
     */
    protected $className;
    
    /**
     * @var string tableName for needed model
     */
    protected $tableName;
    
    public function smoke_model_gen($model = null, $tableName = null)
    {
        global $argv;
        
        if (empty($model)) {
            $model = @$argv[2];
        }

        if (empty($tableName)) {
            $tableName = @$argv[3];
        }
        
        $this->className = $model;
        $this->tableName = $tableName;
        
        ob_start();
        include 'Mad/Script/templates/model.php';
        $ret = ob_get_clean();
        
        // TODO: Think of a better way to do this
        // define the model class the hard way
        $toEval = preg_replace('/^<\?php/', '', $ret);
        eval($toEval);

        $ret = explode("\n", $ret);
        
        // TODO: Change if Mad/Script/templates/model.php is changed
        $prev_argv = $argv;
        unset($argv[3]);
        $argv = array_values($argv);
        $argv[2] = new $model;
        
        //var_dump($docblock_args); die();
        
        $ret[1] = $this->smoke_model_gendocblock();
        
        $argv = $prev_argv;
        unset($prev_argv);
        
        $ret = implode("\n", $ret);
        
        if ($this->_shouldPrint(__FUNCTION__)) {
            echo $ret;
        }
        
        return $ret;
    }
    
    public function smoke_model_gendocblock($model = null, $description = null, $author = null)
    {
        global $argv;
        //var_dump($argv);
        if (empty($model)) {
            $model = @$argv[2];
        }
        
        $model = $this->_modelInstance($model);
        
        if (empty($description)) {
            $description = @$argv[3];
            if (empty($description)) {
                $description = 'Mad model for table `' . $model->tableName() . '`';
            }
        }
        
        if (empty($author)) {
            $author = @$argv[4];
            if (empty($author)) {
                $author = get_current_user();
            }
        }
        
        $properties = $this->smoke_model_gendocblock_properties($model);
        
        $docblock = <<<TEMPLATE
/**
 * {$description}
 *
 * @author ${author}
 *
{$properties}
 */
TEMPLATE;

        if ($this->_shouldPrint(__FUNCTION__)) {
            echo $docblock;
        }
        
        return $docblock;
    }
    
    public function smoke_model_gendocblock_properties($model)
    {
        $ret = array();
        
        foreach ($this->_modelInstance($model)->columns() as $column) {
            /* @var $column Horde_Db_Adapter_Abstract_Column */
            $type = $column->getType();
            $name = '$'.$column->getName();
            $desc = implode(' ', array_filter(array(
                $column->isPrimary() ? 'PRIMARY' : null,
                $column->getSqlType(),
                !$column->isNull() ? 'NOT NULL' : null,
                !is_null($column->getDefault()) ? 'DEFAULT ' . var_export($column->getDefault(), true) : null
            ), 'strlen'));
            
            $ret[] = <<<TEMPLATE
 * @property {$type} {$name} {$desc}
TEMPLATE;
            
        }
        
        $ret = implode("\n", $ret);
        
        if ($this->_shouldPrint(__FUNCTION__)) {
            echo $ret;
        }
        return $ret;
        
    }
    
    /**
     * Converts $model from string to object, creating empty instance
     * @param string|object $model
     * @return Mad_Model_Base
     */
    protected function _modelInstance($model)
    {
        if (!is_object($model)) {
            $model = new $model();
        }
        return $model;
    }
    
    protected function _shouldPrint($taskFunc)
    {
        global $argv;
        $print = false;
        if ($argv[1] == str_replace('_', ':', $taskFunc)) {
            $print = true;
        }
        return $print;
    }
}