<?php

// updated by DevHelper_Helper_ShippableHelper at 2016-08-31T18:51:58+00:00

/**
 * Class bdApi_ShippableHelper_TempFile
 * @version 7
 * @see DevHelper_Helper_ShippableHelper_TempFile
 */
class bdApi_ShippableHelper_TempFile
{
    protected static $_maxDownloadSize = 0;
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

    public static function download($url, array $options = array())
    {
        $options += array(
            'tempFile' => '',
            'userAgent' => '',
            'timeOutInSeconds' => 0,
            'maxRedirect' => 3,
            'maxDownloadSize' => 0,
            'secured' => 0,
        );

        $tempFile = trim(strval($options['tempFile']));
        $managedTempFile = false;
        if (strlen($tempFile) === 0) {
            $tempFile = tempnam(XenForo_Helper_File::getTempDir(), self::_getPrefix());
            self::cache($url, $tempFile);
            $managedTempFile = true;
        }

        if (isset(self::$_cached[$url])
            && filesize(self::$_cached[$url]) > 0
        ) {
            if ($managedTempFile) {
                return self::$_cached[$url];
            } else {
                copy(self::$_cached[$url], $tempFile);
                return $tempFile;
            }
        }

        self::$_maxDownloadSize = $options['maxDownloadSize'];

        $fh = fopen($tempFile, 'wb');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_FILE, $fh);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_NOPROGRESS, 0);
        curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, array(__CLASS__, 'download_curlProgressFunction'));

        if (!empty($options['userAgent'])) {
            curl_setopt($ch, CURLOPT_USERAGENT, $options['userAgent']);
        }
        if ($options['timeOutInSeconds'] > 0) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeOutInSeconds']);
        }
        if ($options['maxRedirect'] > 0) {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, $options['maxRedirect']);
        } else {
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        }
        if ($options['secured'] === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        }

        curl_exec($ch);

        $downloaded = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE)) == 200;

        curl_close($ch);

        fclose($fh);

        if (XenForo_Application::debugMode()) {
            $fileSize = filesize($tempFile);
            if ($downloaded && $fileSize === 0) {
                clearstatcache();
                $fileSize = filesize($tempFile);
            }

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
            file_put_contents($tempFile, '');
            return false;
        }
    }

    public static function download_curlProgressFunction($downloadSize, $downloaded)
    {
        return ((self::$_maxDownloadSize > 0
            && ($downloadSize > self::$_maxDownloadSize
                || $downloaded > self::$_maxDownloadSize))
            ? 1 : 0);
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
