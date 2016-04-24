<?php

class bdApi_Data_TempFileOutput extends XenForo_FileOutput
{
    function __destruct()
    {
        @unlink($this->_fileName);
    }
}