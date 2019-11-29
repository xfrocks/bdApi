<?php

class bdApi_DevHelper_Config extends DevHelper_Config_Base
{
    protected $_dataClasses = array(
        'client' => array(
            'name' => 'client',
            'camelCase' => 'Client',
            'camelCasePlural' => 'Clients',
            'camelCaseWSpace' => 'Client',
            'fields' => array(
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'client_secret' => array(
                    'name' => 'client_secret',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'redirect_uri' => array('name' => 'redirect_uri', 'type' => 'string', 'required' => true),
                'name' => array(
                    'name' => 'name',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'description' => array('name' => 'description', 'type' => 'string', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'options' => array('name' => 'options', 'type' => 'serialized'),
            ),
            'id_field' => 'client_id',
            'title_field' => 'name',
            'primaryKey' => array('client_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_Client',
                    'hash' => 'd06385441f73014290ba478a80e3ae9c',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_Client',
                    'hash' => 'face27cf7250b4997af61fe20ef5c32c',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_Client',
                    'hash' => 'e52763754f4d2ff5c1514c84e933a39f',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_Client',
                    'hash' => 'a4668e14b09a405983ee301966a41fff',
                ),
            ),
        ),
        'token' => array(
            'name' => 'token',
            'camelCase' => 'Token',
            'camelCasePlural' => 'Tokens',
            'camelCaseWSpace' => 'Token',
            'fields' => array(
                'token_id' => array('name' => 'token_id', 'type' => 'uint', 'autoIncrement' => true),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'token_text' => array(
                    'name' => 'token_text',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'expire_date' => array('name' => 'expire_date', 'type' => 'uint', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'scope' => array('name' => 'scope', 'type' => 'string', 'required' => true),
            ),
            'id_field' => 'token_id',
            'title_field' => 'token_text',
            'primaryKey' => array('token_id'),
            'indeces' => array(
                'token_text' => array(
                    'name' => 'token_text',
                    'fields' => array('token_text'),
                    'type' => 'UNIQUE',
                ),
            ),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_Token',
                    'hash' => 'a75baf311ad2a75a3c233f829d2de7fc',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_Token',
                    'hash' => '35c5160ad8faec1951e2290595c601f9',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_Token',
                    'hash' => '3c72b187ee2214d004c93ae1b3bb5943',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_Token',
                    'hash' => '8b47e2671e883fe9339a9808932338df',
                ),
            ),
        ),
        'auth_code' => array(
            'name' => 'auth_code',
            'camelCase' => 'AuthCode',
            'camelCasePlural' => 'AuthCodes',
            'camelCaseWSpace' => 'Auth Code',
            'fields' => array(
                'auth_code_id' => array('name' => 'auth_code_id', 'type' => 'uint', 'autoIncrement' => true),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'auth_code_text' => array(
                    'name' => 'auth_code_text',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'redirect_uri' => array('name' => 'redirect_uri', 'type' => 'string', 'required' => true),
                'expire_date' => array('name' => 'expire_date', 'type' => 'uint', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'scope' => array('name' => 'scope', 'type' => 'string', 'required' => true),
            ),
            'id_field' => 'auth_code_id',
            'title_field' => 'auth_code_text',
            'primaryKey' => array('auth_code_id'),
            'indeces' => array(
                'auth_code_text' => array(
                    'name' => 'auth_code_text',
                    'fields' => array('auth_code_text'),
                    'type' => 'UNIQUE',
                ),
            ),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_AuthCode',
                    'hash' => '166891f59a3603dc9969b591629f29bc',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_AuthCode',
                    'hash' => '45e95ebc1bbee53f3972a059f4327a15',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_AuthCode',
                    'hash' => '6ae4cf1aafaf43f7ae7cd5d9d0a0df9b',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_AuthCode',
                    'hash' => '42e51ecb001ff9a0faea674641568d41',
                ),
            ),
        ),
        'refresh_token' => array(
            'name' => 'refresh_token',
            'camelCase' => 'RefreshToken',
            'camelCasePlural' => 'RefreshTokens',
            'camelCaseWSpace' => 'Refresh Token',
            'fields' => array(
                'refresh_token_id' => array(
                    'name' => 'refresh_token_id',
                    'type' => 'uint',
                    'autoIncrement' => true,
                ),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'refresh_token_text' => array(
                    'name' => 'refresh_token_text',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'expire_date' => array('name' => 'expire_date', 'type' => 'uint', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'scope' => array('name' => 'scope', 'type' => 'string', 'required' => true),
            ),
            'id_field' => 'refresh_token_id',
            'title_field' => 'refresh_token_text',
            'primaryKey' => array('refresh_token_id'),
            'indeces' => array(
                'refresh_token_text' => array(
                    'name' => 'refresh_token_text',
                    'fields' => array('refresh_token_text'),
                    'type' => 'UNIQUE',
                ),
            ),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_RefreshToken',
                    'hash' => 'c6db6bb1109926ca4eba261031cfc65c',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_RefreshToken',
                    'hash' => '63c1f959d0711741936bfe1bd8226484',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_RefreshToken',
                    'hash' => '785792de37c97cca302374430d5c5063',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_RefreshToken',
                    'hash' => '395fece4ddeab05829611c375694ebec',
                ),
            ),
        ),
        'log' => array(
            'name' => 'log',
            'camelCase' => 'Log',
            'camelCasePlural' => 'Logs',
            'camelCaseWSpace' => 'Log',
            'fields' => array(
                'log_id' => array('name' => 'log_id', 'type' => 'uint', 'autoIncrement' => true),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'ip_address' => array(
                    'name' => 'ip_address',
                    'type' => 'string',
                    'length' => 50,
                    'required' => true,
                ),
                'request_date' => array('name' => 'request_date', 'type' => 'uint', 'required' => true),
                'request_method' => array(
                    'name' => 'request_method',
                    'type' => 'string',
                    'length' => 10,
                    'required' => true,
                ),
                'request_uri' => array('name' => 'request_uri', 'type' => 'string'),
                'request_data' => array('name' => 'request_data', 'type' => 'serialized'),
                'response_code' => array('name' => 'response_code', 'type' => 'uint', 'required' => true),
                'response_output' => array('name' => 'response_output', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'id_field' => 'log_id',
            'title_field' => 'client_id',
            'primaryKey' => array('log_id'),
            'indeces' => array(),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_Log',
                    'hash' => 'c52e845427612ba05a46c935ad80c7db',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_Log',
                    'hash' => 'cb3417127ecae72d04c607a18bf77853',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_Log',
                    'hash' => 'cdc2b71157553793ce20112afcb1aa98',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_Log',
                    'hash' => 'd2546249050e790a816e85a98c2f45b8',
                ),
            ),
        ),
        'subscription' => array(
            'name' => 'subscription',
            'camelCase' => 'Subscription',
            'camelCasePlural' => 'Subscriptions',
            'camelCaseWSpace' => 'Subscription',
            'camelCasePluralWSpace' => 'Subscriptions',
            'fields' => array(
                'subscription_id' => array(
                    'name' => 'subscription_id',
                    'type' => 'uint',
                    'autoIncrement' => true,
                ),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'callback' => array('name' => 'callback', 'type' => 'string'),
                'topic' => array(
                    'name' => 'topic',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'subscribe_date' => array('name' => 'subscribe_date', 'type' => 'uint', 'required' => true),
                'expire_date' => array(
                    'name' => 'expire_date',
                    'type' => 'uint',
                    'required' => true,
                    'default' => 0,
                ),
            ),
            'phrases' => array(),
            'id_field' => 'subscription_id',
            'title_field' => 'client_id',
            'primaryKey' => array('subscription_id'),
            'indeces' => array(
                'client_id' => array(
                    'name' => 'client_id',
                    'fields' => array('client_id'),
                    'type' => 'NORMAL',
                ),
                'topic' => array('name' => 'topic', 'fields' => array('topic'), 'type' => 'NORMAL'),
            ),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_Subscription',
                    'hash' => 'd22acd9b533d5303f8609f45c18989b3',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_Subscription',
                    'hash' => '6da4fa7e7fd37d2037fef3e04a758e7b',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_Subscription',
                    'hash' => 'a4b9bf9d0e002a1fd377800f05f604bf',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_Subscription',
                    'hash' => '27d424a7d03a8ca84f5b2ec1b84a0bf0',
                ),
            ),
        ),
        'user_scope' => array(
            'name' => 'user_scope',
            'camelCase' => 'UserScope',
            'camelCasePlural' => 'UserScopes',
            'camelCaseWSpace' => 'User Scope',
            'camelCasePluralWSpace' => 'User Scopes',
            'fields' => array(
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'scope' => array(
                    'name' => 'scope',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'accept_date' => array('name' => 'accept_date', 'type' => 'uint', 'required' => true),
            ),
            'phrases' => array(),
            'id_field' => 'user_id',
            'title_field' => 'client_id',
            'primaryKey' => false,
            'indeces' => array(
                'user_id' => array(
                    'name' => 'user_id',
                    'fields' => array('user_id'),
                    'type' => 'NORMAL',
                ),
            ),
            'files' => array(
                'data_writer' => false,
                'model' => false,
                'route_prefix_admin' => false,
                'controller_admin' => false,
            ),
        ),
        'client_content' => array(
            'name' => 'client_content',
            'camelCase' => 'ClientContent',
            'camelCasePlural' => 'ClientContents',
            'camelCaseWSpace' => 'Client Content',
            'camelCasePluralWSpace' => 'Client Contents',
            'fields' => array(
                'client_content_id' => array(
                    'name' => 'client_content_id',
                    'type' => 'uint',
                    'autoIncrement' => true,
                ),
                'client_id' => array(
                    'name' => 'client_id',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'content_type' => array(
                    'name' => 'content_type',
                    'type' => 'string',
                    'length' => 25,
                    'required' => true,
                ),
                'content_id' => array('name' => 'content_id', 'type' => 'uint', 'required' => true),
                'title' => array(
                    'name' => 'title',
                    'type' => 'string',
                    'length' => 255,
                    'required' => true,
                ),
                'body' => array('name' => 'body', 'type' => 'string', 'required' => true),
                'date' => array('name' => 'date', 'type' => 'uint', 'required' => true),
                'link' => array('name' => 'link', 'type' => 'string', 'required' => true),
                'user_id' => array('name' => 'user_id', 'type' => 'uint', 'required' => true),
                'extra_data' => array('name' => 'extra_data', 'type' => 'serialized'),
            ),
            'phrases' => array(),
            'title_field' => 'client_id',
            'primaryKey' => array('client_content_id'),
            'indeces' => array(
                'content_type_content_id' => array(
                    'name' => 'content_type_content_id',
                    'fields' => array('content_type', 'content_id'),
                    'type' => 'NORMAL',
                ),
            ),
            'files' => array(
                'data_writer' => array(
                    'className' => 'bdApi_DataWriter_ClientContent',
                    'hash' => '768e9d0f0daf1b06d922c0a7fb85e948',
                ),
                'model' => array(
                    'className' => 'bdApi_Model_ClientContent',
                    'hash' => 'f5d7ecc874121a8b1f796dd619d69aaa',
                ),
                'route_prefix_admin' => array(
                    'className' => 'bdApi_Route_PrefixAdmin_ClientContent',
                    'hash' => 'c349ce827df98df310a98d4505f86356',
                ),
                'controller_admin' => array(
                    'className' => 'bdApi_ControllerAdmin_ClientContent',
                    'hash' => 'b5b8ae7006c06b41736cb7da82eb5d1d',
                ),
            ),
        ),
    );
    protected $_dataPatches = array(
        'xf_bdapi_token' => array(
            'issue_date' => array(
                'name' => 'issue_date',
                'type' => 'uint',
                'required' => true,
                'default' => 0,
            ),
        ),
        'xf_bdapi_auth_code' => array(
            'issue_date' => array(
                'name' => 'issue_date',
                'type' => 'uint',
                'required' => true,
                'default' => 0,
            ),
        ),
        'xf_bdapi_refresh_token' => array(
            'issue_date' => array(
                'name' => 'issue_date',
                'type' => 'uint',
                'required' => true,
                'default' => 0,
            ),
        ),
        'xf_bdapi_log' => array(
            'index::request_date' => array(
                'index' => true,
                'fields' => array('request_date'),
                'name' => 'request_date',
                'type' => 'NORMAL',
            ),
        ),
    );
    protected $_exportPath = '/Users/sondh/XenForo/bdApi';
    protected $_exportIncludes = array('api');
    protected $_exportExcludes = array('library/bdApi/Lib/oauth2-server-php/test');
    protected $_exportAddOns = array();
    protected $_exportStyles = array();
    protected $_options = array();

    /**
     * Return false to trigger the upgrade!
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
            array('primary_key_1', 'primary_key_2'), // or 'primary_key', both are okie
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
