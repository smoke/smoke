<?php
class Smoke_Zend_Filter_File_RenameToUnique extends Zend_Filter_File_Rename
{
    public function _getFileName($file)
    {
        $rename = parent::_getFileName($file);
        if (!isset($rename['source'])) {
            return $rename;
        }
        
        $rename['target'] = 
            dirname($rename['target']) 
            . DIRECTORY_SEPARATOR 
            . implode('.', array_filter(array(uniqid(), pathinfo($rename['target'], PATHINFO_EXTENSION))));
        
        return $rename;
    }
}