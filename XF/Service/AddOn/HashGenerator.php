<?php

namespace Xfrocks\Api\XF\Service\AddOn;

class HashGenerator extends XFCP_HashGenerator
{
    /**
     * @return void
     */
    protected function prepareFilesToHash()
    {
        if ($this->filesPrepared) {
            return;
        }

        parent::prepareFilesToHash();

        $keys = [];
        foreach ($this->filesToHash as $key => $path) {
            if (preg_match('#_build/upload/api/[^/]+$#', $path) !== 1) {
                continue;
            }

            $keys[] = $key;
        }

        foreach ($keys as $key) {
            unset($this->filesToHash[$key]);
        }
    }
}
