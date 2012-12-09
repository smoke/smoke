<?php
/**
 * Zend Paginator Adapter for working with MadModels
 * 
 * @author        Radoslav Kirilov <https://github.com/smoke>
 * @copyright     Radoslav Kirilov <https://github.com/smoke>
 */
class Smoke_Zend_Paginator_Adapter_MadModel implements Zend_Paginator_Adapter_Interface
{
    /**
     * Mad Model
     * @var Mad_Model_Base
     */
    protected $_model;
    
    /**
     * @var mixed @see Mad_Model_Base::find('all') $options
     */
    protected $_paginateOptions;
    
    /**
     * @var mixed @see Mad_Model_Base::find('all') $bindVars
     */
    protected $_paginateBindVars;
    
    /**
     * Total items count cache
     * @var int|null
     */
    protected $_totalItemsCount;
    
    /**
     * Constructs a zend paginator adapter for given MadModel and options
     * @param string $model The model class that will be paginated
     * @param array|null $paginateOptions optional The options that are almost transparently given to Mad_Model_Base::find('all') and Mad_Model_Base::count()
     * @param array|null $paginateBindVars optional The bind vars that will be used for query placeholders for Mad_Model_Base::paginate() and Mad_Model_Base::count()
     */
    public function __construct($model, $paginateOptions = null, $paginateBindVars = null)
    {
        $this->_model = $model;
        $this->_paginateOptions = $paginateOptions;
        $this->_paginateBindVars = $paginateBindVars;
    }
    
    /**
     * Returns an collection of items for a page.
     *
     * @param  integer $offset Page offset
     * @param  integer $itemCountPerPage Number of items per page
     * @return array
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $options = array(
            'offset' => $offset,
            'limit' => $itemCountPerPage
        ) + (array) $this->_paginateOptions;
        
        return call_user_func(array($this->_model, 'find'), 'all', $options, $this->_paginateBindVars); 
    }
    
    public function count($refresh = false)
    {
        if ($refresh) {
            $this->_totalItemsCount = null;
        }
        
        if (is_null($this->_totalItemsCount)) {
            $options = $this->_paginateOptions;
            if ($options) {
                unset($options['page'], $options['perPage'], $options['select']);
            }
        
            $this->_totalItemsCount = (int) call_user_func(array($this->_model, 'count'), $options, $this->_paginateBindVars);
            
        }
        
        return $this->_totalItemsCount;
    }
}
