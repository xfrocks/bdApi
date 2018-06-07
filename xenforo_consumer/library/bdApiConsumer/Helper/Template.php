<?php

class bdApiConsumer_Helper_Template
{
    public static function getProviderSdkJs($provider, $prefix)
    {
        $root = $provider['root'];

        if (XenForo_Application::$secure) {
            $http = 'http://';
            $https = 'https://';
            if (strpos($root, $http) === 0) {
                // we are running under https but the provider root is http
                // switching to https for now
                // TODO: option
                $root = substr_replace($root, $https, 0, strlen($http));
            }
        }

        return sprintf('%s/index.php?assets/sdk.js&prefix=%s', $root, $prefix);
    }

    public static function renderProviderInLoop($template)
    {
        $output = '';

        $providers = bdApiConsumer_Option::getProviders();
        foreach ($providers as $provider) {
            $mapping = array();

            foreach (array_keys($provider) as $providerKey) {
                if (!is_string($provider[$providerKey])) {
                    continue;
                }

                $placeholder = sprintf('{provider.%s}', $providerKey);
                $mapping[$placeholder] = $provider[$providerKey];
                $mapping[rawurlencode($placeholder)] = $provider[$providerKey];
            }

            $output .= str_replace(array_keys($mapping), array_values($mapping), $template);
        }

        return $output;
    }
}
