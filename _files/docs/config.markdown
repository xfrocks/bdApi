# Configuration Documents

## config.php

It's possible to reconfigure some internal api values by adding something like this into `config.php`:

```php
$config['api'] = [
  'configKey' => 'configValue',
  'configKey2' => 'someValue',
  ...
];
```

 * `accessTokenParamKey` default=`oauth_token`
 * `apiDirName` default=`api`
 * `routerType` default=`XfrocksApi`
 * `scopeDelimiter` default=` ` (space)
