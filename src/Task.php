<?php
/*
 -------------------------------------------------------------------------
 Deploy plugin for GLPI
 Copyright (C) 2022 by the Deploy Development Team.

 https://github.com/pluginsGLPI/deploy
 -------------------------------------------------------------------------

 LICENSE

 This file is part of Deploy.

 Deploy is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 Deploy is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with Deploy. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

namespace GlpiPlugin\Deploy;

use Agent;
use CommonDBTM;
use DBConnection;
use Migration;

class Task extends CommonDBTM
{
    public static $rightname = 'entity';

    public static function getTypeName($nb = 0)
    {
        return _n('Task', 'Tasks', $nb, 'deploy');
    }

    public function defineTabs($options = [])
    {
        $tabs = [];
        $this->addDefaultFormTab($tabs);
        $this->addStandardTab(Task_Package::class, $tabs, $options);
        $this->addStandardTab(Task_Target::class, $tabs, $options);

        return $tabs;
    }

    public static function getIcon()
    {
        return 'ti ti-list-check';
    }


    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Task_Package::class,
                Task_Target::class,
            ]
        );
    }

    public static function install(Migration $migration)
    {
        global $DB;

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $default_charset   = DBConnection::getDefaultCharset();
            $default_collation = DBConnection::getDefaultCollation();
            $sign              = DBConnection::getDefaultPrimaryKeySignOption();

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` int $sign NOT NULL AUTO_INCREMENT,
                `entities_id` int $sign NOT NULL DEFAULT '0',
                `is_recursive` tinyint NOT NULL DEFAULT '0',
                `name` varchar(255) DEFAULT NULL,
                `is_deleted` tinyint NOT NULL DEFAULT '0',
                `is_active` tinyint NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                `comment` text,
                PRIMARY KEY (`id`),
                KEY `name` (`name`),
                KEY `date_creation` (`date_creation`),
                KEY `date_mod` (`date_mod`),
                KEY `is_active` (`is_active`),
                KEY `is_deleted` (`is_deleted`),
                KEY `entities_id` (`entities_id`),
                KEY `is_recursive` (`is_recursive`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }

        // add display preferences
        /* $nb_display_pref = countElementsInTable(DisplayPreference::getTable(), [
            'itemtype' => self::getType()
        ]);
        if ($nb_display_pref == 0) {
            $dp = new DisplayPreference;
            $i  = 1;
            foreach ([1, 80, 121, 19] as $id_so) {
                $dp->add([
                    'itemtype' => self::getType(),
                    'num'      => $id_so,
                    'rank'     => $i,
                    'users_id' => 0,
                ]);
                $i++;
            }
        } */
    }


    public static function uninstall(Migration $migration)
    {
        global $DB;

        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);

        $DB->query("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` = '" . self::getType() . "'");
    }

    public static function handleDeployTask(array $params)
    {
        $deploy = plugin_version_deploy();
        $deploy_name = strtolower($deploy['name']);
        $params['options']['response'][$deploy_name] = [
            'version' => PLUGIN_DEPLOY_VERSION,
            'server' => $deploy_name,
            'deploy_config_page' => 'plugins/deploy/front/deploytask.php',
        ];

        return $params;
    }

    /**
    * Manage communication between agent and server
    *
    * @param array $params
    * @return array|false array return jobs ready for the agent
    */
    public static function collectTask($params = [])
    {
        $logContent = print_r($params, true);
        $response = [];
        if (isset($params['action']) && isset($params['machineid'])) {
            $agent = new Agent();
            if ($agent->getFromDBByCrit(['deviceid' => $params['machineid']])) {
                if ($params['action'] == 'getConfig') {
                    $response = self::getConfigAction($params);
                }
            }
        }
        if (!empty($response)) {
            $response = json_encode($response);
        }
        return $response;
    }

    public static function getConfigAction($agent, $params = [])
    {
        return ['configValidityPeriod' => 600, 'schedule' => []];
    }
}
