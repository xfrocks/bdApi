<?php

class bdApiConsumer_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array();
    protected $_dataPatches = array(
        'xf_user_profile' => array(
            'bdapiconsumer_unused' => array('name' => 'bdapiconsumer_unused', 'type' => 'string', 'length' => '255')
        )
    );
    protected $_exportPath = '/Users/sondh/XenForo/bdApiConsumer/';
    protected $_exportIncludes = array();

    /**
     * Return false to trigger the upgrade!
     * common use methods:
     *    public function addDataClass($name, $fields = array(), $primaryKey = false, $indeces = array())
     *    public function addDataPatch($table, array $field)
     *    public function setExportPath($path)
     **/
    protected function _upgrade()
    {
        return true; // remove this line to trigger update

        /*
        $this->addDataClass(
            'name_here',
            array( // fields
                'field_here' => array(
                    'type' => 'type_here',
                    // 'length' => 'length_here',
                    // 'required' => true,
                    // 'allowedValues' => array('value_1', 'value_2'),
                    // 'default' => 0,
                    // 'autoIncrement' => true,
                ),
                // other fields go here
            ),
            'primary_key_field_here',
            array( // indeces
                array(
                    'fields' => array('field_1', 'field_2'),
                    'type' => 'NORMAL', // UNIQUE or FULLTEXT
                ),
            ),
        );
        */
    }
}
