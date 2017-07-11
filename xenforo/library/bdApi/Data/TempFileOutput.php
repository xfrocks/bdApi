<?php

class bdApi_Data_TempFileOutput extends XenForo_FileOutput
{
    public function __destruct()
    {
        @unlink($this->_fileName);
    }
}
