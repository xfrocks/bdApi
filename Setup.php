<?php

namespace Xfrocks\Api;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    use StepRunnerInstallTrait;
    use StepRunnerUpgradeTrait;
    use StepRunnerUninstallTrait;

    public function installStep1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);
        }
    }

    public function uninstallStep1()
    {
        $sm = $this->schemaManager();

        foreach (array_keys($this->getTables()) as $tableName) {
            $sm->dropTable($tableName);
        }
    }

    public function upgrade2000012Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_bdapi_auth_code', function (Alter $table) {
            $table->changeColumn('client_id', 'varbinary')->length(255);
            $table->changeColumn('auth_code_text', 'varbinary')->length(255);
        });

        $sm->alterTable('xf_bdapi_client', function (Alter $table) {
            $table->changeColumn('client_id', 'varbinary')->length(255);
            $table->changeColumn('client_secret', 'varbinary')->length(255);
            $table->changeColumn('name', 'text');

            $table->convertCharset('utf8mb4');
        });

        $sm->alterTable('xf_bdapi_refresh_token', function (Alter $table) {
            $table->changeColumn('client_id', 'varbinary')->length(255);
            $table->changeColumn('refresh_token_text', 'varbinary')->length(255);
            $table->changeColumn('scope', 'blob');
        });

        $sm->alterTable('xf_bdapi_token', function (Alter $table) {
            $table->changeColumn('client_id', 'varbinary')->length(255);
            $table->changeColumn('token_text', 'varbinary')->length(255);
            $table->changeColumn('scope', 'blob');
        });
    }

    public function upgrade2000013Step1()
    {
        $sm = $this->schemaManager();

        $sm->alterTable('xf_bdapi_auth_code', function (Alter $table) {
            $table->addIndex()->type('key')->columns('expire_date');
        });

        $sm->alterTable('xf_bdapi_client', function (Alter $table) {
            $table->addIndex()->type('key')->columns('user_id');
        });

        $sm->alterTable('xf_bdapi_refresh_token', function (Alter $table) {
            $table->addIndex()->type('key')->columns('expire_date');
        });

        $sm->alterTable('xf_bdapi_token', function (Alter $table) {
            $table->addIndex()->type('key')->columns('client_id');
            $table->addIndex()->type('key')->columns('expire_date');
            $table->addIndex()->type('key')->columns('user_id');
        });
    }

    public function upgrade2000014Step1()
    {
        $sm = $this->schemaManager();

        foreach ($this->getTables2() as $tableName => $closure) {
            $sm->createTable($tableName, $closure);
        }

        $sm->alterTable('xf_bdapi_user_scope', function (Alter $table) {
            $table->changeColumn('client_id', 'varbinary')->length(255);
            $table->changeColumn('scope', 'varbinary')->length(255);

            $table->addIndex()->type('unique')->columns(['client_id', 'user_id', 'scope']);
        });
    }

    private function getTables()
    {
        $tables = [];

        $tables += $this->getTables1();
        $tables += $this->getTables2();
        $tables += $this->getTables3();

        return $tables;
    }

    private function getTables1()
    {
        $tables = [];

        $tables['xf_bdapi_auth_code'] = function (Create $table) {
            $table->addColumn('auth_code_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varbinary')->length(255);
            $table->addColumn('auth_code_text', 'varbinary')->length(255);
            $table->addColumn('redirect_uri', 'text');
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'text');

            $table->addUniqueKey('auth_code_text');
        };

        $tables['xf_bdapi_client'] = function (Create $table) {
            $table->addColumn('client_id', 'varbinary')->length(255)->primaryKey();
            $table->addColumn('client_secret', 'varbinary')->length(255);
            $table->addColumn('redirect_uri', 'text');
            $table->addColumn('name', 'text');
            $table->addColumn('description', 'text');
            $table->addColumn('user_id', 'int');
            $table->addColumn('options', 'mediumblob')->nullable(true);
        };

        $tables['xf_bdapi_refresh_token'] = function (Create $table) {
            $table->addColumn('refresh_token_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varbinary')->length(255);
            $table->addColumn('refresh_token_text', 'varbinary')->length(255);
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'blob');

            $table->addUniqueKey('refresh_token_text');
        };

        $tables['xf_bdapi_token'] = function (Create $table) {
            $table->addColumn('token_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varbinary')->length(255);
            $table->addColumn('token_text', 'varbinary')->length(255);
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'blob');

            $table->addUniqueKey('token_text');
        };

        return $tables;
    }

    private function getTables2()
    {
        $tables = [];

        $tables['xf_bdapi_user_scope'] = function (Create $table) {
            $table->addColumn('client_id', 'varbinary')->length(255);
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'varbinary')->length(255);
            $table->addColumn('accept_date', 'int');

            $table->addKey('user_id');
            $table->addUniqueKey(['client_id', 'user_id', 'scope']);
        };

        return $tables;
    }

    private function getTables3()
    {
        $tables = [];

        $tables['xf_bdapi_subscription'] = function (Create $table) {
            $table->addColumn('subscription_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varchar', 255);
            $table->addColumn('callback', 'text');
            $table->addColumn('topic', 'varchar', 255);
            $table->addColumn('subscrie_date', 'int')->unsigned();
            $table->addColumn('expire_date', 'int')->unsigned()->setDefault(0);

            $table->addKey('client_id');
            $table->addKey('topic');
        };

        $tables['xf_bdapi_log'] = function(Create $table) {
            $table->addColumn('log_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id','varchar', 255);
            $table->addColumn('user_id', 'int')->unsigned();
            $table->addColumn('ip_address', 'varchar',50);
            $table->addColumn('request_date', 'int')->unsigned();
            $table->addColumn('request_method', 'varchar', 10);
            $table->addColumn('request_uri', 'text');
            $table->addColumn('request_data', 'mediumblob');
            $table->addColumn('response_code', 'int')->unsigned();
            $table->addColumn('response_output', 'mediumblob');
        };

        return $tables;
    }
}
