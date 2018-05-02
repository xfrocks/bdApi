<?php

namespace Xfrocks\Api;

use XF\AddOn\AbstractSetup;
use XF\AddOn\StepRunnerInstallTrait;
use XF\AddOn\StepRunnerUninstallTrait;
use XF\AddOn\StepRunnerUpgradeTrait;
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

    private function getTables()
    {
        $tables = [];

        $tables['xf_bdapi_auth_code'] = function (Create $table) {
            $table->addColumn('auth_code_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varchar')->length(255);
            $table->addColumn('auth_code_text', 'varchar')->length(190);
            $table->addColumn('redirect_uri', 'text');
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'text');

            $table->addUniqueKey('auth_code_text');
        };

        $tables['xf_bdapi_client'] = function (Create $table) {
            $table->addColumn('client_id', 'varchar')->length(190)->primaryKey();
            $table->addColumn('client_secret', 'varchar')->length(255);
            $table->addColumn('redirect_uri', 'text');
            $table->addColumn('name', 'varchar')->length(255);
            $table->addColumn('description', 'text');
            $table->addColumn('user_id', 'int');
            $table->addColumn('options', 'mediumblob')->nullable(true);
        };

        $tables['xf_bdapi_refresh_token'] = function (Create $table) {
            $table->addColumn('refresh_token_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varchar')->length(255);
            $table->addColumn('refresh_token_text', 'varchar')->length(190);
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'text');

            $table->addUniqueKey('refresh_token_text');
        };

        $tables['xf_bdapi_token'] = function (Create $table) {
            $table->addColumn('token_id', 'int')->autoIncrement()->primaryKey();
            $table->addColumn('client_id', 'varchar')->length(255);
            $table->addColumn('token_text', 'varchar')->length(190);
            $table->addColumn('expire_date', 'int');
            $table->addColumn('user_id', 'int');
            $table->addColumn('scope', 'text');

            $table->addUniqueKey('token_text');
        };

        return $tables;
    }
}
