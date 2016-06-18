<?php

// updated by DevHelper_Helper_ShippableHelper at 2016-06-18T11:13:03+00:00

/**
 * Class bdApiConsumer_ShippableHelper_TempFile
 * @version 4
 * @see DevHelper_Helper_ShippableHelper_TempFile
 */
class bdApiConsumer_ShippableHelper_TempFile
{
    protected static $_cached = array();
    protected static $_registeredShutdownFunction = false;

    public static function cache($url, $tempFile)
    {
        self::$_cached[$url] = $tempFile;

        if (!self::$_registeredShutdownFunction) {
            register_shutdown_function(array(__CLASS__, 'deleteAllCached'));
        }
    }

    public static function create($contents)
    {
        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), self::_getPrefix());
        self::cache(sprintf('%s::%s', __METHOD__, md5($tempFile)), $tempFile);

        file_put_contents($tempFile, $contents);

        return $tempFile;
    }

    public static function download($url)
    {
        if (isset(self::$_cached[$url]) AND file_exists(self::$_cached[$url])) {
            // use cached temp file, no need to re-download
            return self::$_cached[$url];
        }

        $tempFile = tempnam(XenForo_Helper_File::getTempDir(), self::_getPrefix());
        self::cache($url, $tempFile);

        $fh = fopen($tempFile, 'wb');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);

        $downloaded = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)) == 200;

        curl_close($ch);

        fclose($fh);

        if (XenForo_Application::debugMode()) {
            $fileSize = filesize($tempFile);

            XenForo_Helper_File::log(__CLASS__, call_user_func_array('sprintf', array(
                'download %s -> %s, %s, %d bytes%s',
                $url,
                $tempFile,
                ($downloaded ? 'succeeded' : 'failed'),
                $fileSize,
                ((!$downloaded && $fileSize > 0) ? "\n\t" . file_get_contents($tempFile) : ''),
            )));
        }

        if ($downloaded) {
            return $tempFile;
        } else {
            return false;
        }
    }

    public static function deleteAllCached()
    {
        foreach (self::$_cached as $url => $tempFile) {
            if (XenForo_Application::debugMode()) {
                $fileSize = @filesize($tempFile);
            }

            $deleted = @unlink($tempFile);

            if (XenForo_Application::debugMode()) {
                XenForo_Helper_File::log(__CLASS__, call_user_func_array('sprintf', array(
                    'delete %s -> %s, %s, %d bytes',
                    $url,
                    $tempFile,
                    ($deleted ? 'succeeded' : 'failed'),
                    (!empty($fileSize) ? $fileSize : 0),
                )));
            }
        }

        self::$_cached = array();
    }

    protected static function _getPrefix()
    {
        static $prefix = null;

        if ($prefix === null) {
            $prefix = strtolower(preg_replace('#[^A-Z]#', '', __CLASS__)) . '_';
        }

        return $prefix;
    }

}
