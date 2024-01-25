<?php

/**
 * -------------------------------------------------------------------------
 * Deploy plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of Deploy.
 *
 * Deploy is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * Deploy is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Deploy. If not, see <http://www.gnu.org/licenses/>.
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2022-2024 by Deploy plugin team.
 * @license   GPLv3 https://www.gnu.org/licenses/gpl-3.0.html
 * @link      https://github.com/pluginsGLPI/deploy
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Deploy;

use CommonDBRelation;
use CommonGLPI;
use DBConnection;
use Glpi\Application\View\TemplateRenderer;
use Migration;

class Task_Target extends CommonDBRelation
{
    public static $itemtype_1 = Task::class;
    public static $items_id_1 = "plugin_deploy_tasks_id";

    public static $itemtype_2 = "itemtype";
    public static $items_id_2 = "items_id";

    public static function getTypeName($nb = 0)
    {
        return _n("Target", "Targets", $nb, "deploy");
    }


    public static function getIcon()
    {
        return "ti ti-devices-pc";
    }


    static function getItemtypes(): array
    {
        return [
            'Computer',
            'Group',
            'SavedSearch',
        ];
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        $number = self::countForMainItem($item);

        switch ($item->getType()) {
            case Task::class:
                return self::createTabEntry(self::getTypeName($number), $number);
        }

        return parent::getTabNameForItem($item, $withtemplate);
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($item->getType()) {
            case Task::class:
                return self::showForTask($item);
        }

        return parent::displayTabContentForItem($item, $tabnum, $withtemplate);
    }


    public static function showForTask(Task $task)
    {
        global $DB;

        $iterator = $DB->request([
            'FROM'  => self::getTable(),
            'WHERE' => [
                'plugin_deploy_tasks_id' => $task->fields['id']
            ],
        ]);

        $targets = [];
        $itemtypes = self::getItemtypes();
        $used = array_combine($itemtypes, array_fill(0, count($itemtypes), []));
        foreach ($iterator as $target) {
            $item = new $target['itemtype']();
            $item->getFromDB($target['items_id']);
            $targets[$target['id']] = $item;
            $used[$target['itemtype']][$target['items_id']] = $target['items_id'];
        }

        TemplateRenderer::getInstance()->display('@deploy/task/target.list.html.twig', [
            'task'        => $task,
            'subitems'    => $targets,
            'used'        => $used,
            'none_found'  => __("No target found for this task", 'deploy'),
            'task_active' => $task->fields['is_active'],
            'ma_itemtype' => self::class,
        ]);
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

            $task_fk = getForeignKeyFieldForItemType(Task::class);

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` int $sign NOT NULL AUTO_INCREMENT,
                `$task_fk` int $sign NOT NULL DEFAULT '0',
                `itemtype` varchar(100) DEFAULT NULL,
                `items_id` int $sign NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `$task_fk` (`$task_fk`),
                UNIQUE KEY `item` (`itemtype`,`items_id`),
                KEY `date_creation` (`date_creation`),
                KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->request($query) or die($DB->error());
        }
    }


    public static function uninstall(Migration $migration)
    {
        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);
    }
}

