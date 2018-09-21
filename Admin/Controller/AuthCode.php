<?php

namespace Xfrocks\Api\Admin\Controller;


class AuthCode extends Entity
{
    protected function getShortName()
    {
        return 'Xfrocks\Api:AuthCode';
    }

    protected function getPrefixForPhrases()
    {
        return 'bdapi_auth_code';
    }

    protected function getRoutePrefix()
    {
        return 'api-auth-codes';
    }
}
