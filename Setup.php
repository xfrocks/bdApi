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

    private function getTables()
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
}
