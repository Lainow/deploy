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
use Computer;
use Dropdown;
use Entity;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Search;
use Session;
use Toolbox;

class Computer_Group_Static extends CommonDBRelation
{

    // From CommonDBRelation
    public static $itemtype_1 = 'GlpiPlugin\Deploy\ComputerGroup';
    public static $items_id_1 = 'plugin_deploy_computers_groups_id';
    public static $itemtype_2 = 'Computer';
    public static $items_id_2 = 'computers_id';

    public static $checkItem_2_Rights  = self::DONT_CHECK_ITEM_RIGHTS;
    public static $logs_for_item_2     = false;
    public $auto_message_on_action     = false;

    public static $rightname  = 'computer_group';


    public static function getTypeName($nb = 0)
    {
        return _n('Static groups', 'Static group', $nb, 'deploy');
    }


    public static function canCreate()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }


    public function canCreateItem()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }


    public static function canPurge()
    {
        return Session::haveRight(static::$rightname, UPDATE);
        return true;
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (get_class($item) == ComputerGroup::getType()) {
            $count = 0;
            $count = countElementsInTable(self::getTable(), ['plugin_deploy_computers_groups_id' => $item->getID()]);
            $ong = [];
            $ong[1] = self::createTabEntry(Self::getTypeName(Session::getPluralNumber()), $count);
            return $ong;
        }
        return '';
    }


    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0)
    {
        switch ($tabnum) {
            case 1:
                self::showForItem($item);
                break;
        }
        return true;
    }


    private static function getheadings(): array
    {
        return [
            'name' => __('Name'),
            'is_dynamic' => __('Automatic inventory'),
            'entities_id' => Entity::getTypeName(1),
            'serial' => __('Serial number'),
            'otherserial' => __('Inventory number'),
        ];
    }


    public static function showForItem(ComputerGroup $computergroup)
    {
        global $DB;

        $ID = $computergroup->getField('id');
        if (!$computergroup->can($ID, UPDATE)) {
            return false;
        }

        $datas = [];
        $used  = [];
        $params = [
            'SELECT' => '*',
            'FROM'   => self::getTable(),
            'WHERE'  => ['plugin_deploy_computers_groups_id' => $ID],
        ];

        $iterator = $DB->request($params);
        foreach ($iterator as $data) {
            $datas[] = $data;
            $used[] = $data['computers_id'];
        }

        if ($computergroup->canAddItem('itemtype')) {
            TemplateRenderer::getInstance()->display('@deploy/computergroup/computer_group_static.html.twig', [
                'form_action'  => Toolbox::getItemTypeFormURL("GlpiPlugin\Deploy\ComputerGroup"),
                'computers_groups_id' => $ID,
                'computer_used' => $used,
                'params'       => [],
            ]);
        }

        $canread = $computergroup->can($ID, READ);
        $rows = [];
        if ($canread) {
            foreach ($datas as $data) {
                $row = [];

                $computer = new Computer();
                $computer->getFromDB($data["computers_id"]);
                $linkname = $computer->fields["name"];
                $itemtype = Computer::getType();
                if ($_SESSION["glpiis_ids_visible"] || empty($computer->fields["name"])) {
                    $linkname = sprintf(__('%1$s (%2$s)'), $linkname, $computer->fields["id"]);
                }
                $link = $itemtype::getFormURLWithID($computer->fields["id"]);
                $name = "<a href=\"" . $link . "\">" . $linkname . "</a>";

                $row['name'] = $name;
                $row['id'] = $data["id"];
                $row['is_deleted'] = $computer->fields["is_deleted"];
                $row['is_dynamic'] = Dropdown::getYesNo($computer->fields['is_dynamic']);
                $row['entity'] = Dropdown::getDropdownName("glpi_entities", $computer->fields['entities_id']);
                $row['serial'] = (isset($computer->fields["serial"]) ? "" . $computer->fields["serial"] . "" : "-");
                $row['otherserial'] = (isset($computer->fields["otherserial"]) ? "" . $computer->fields["otherserial"] . "" : "-");
                $rows[] = $row;
            }

            TemplateRenderer::getInstance()->display('@deploy/computergroup/computer_group_static_list.html.twig', [
                'subitem_type'                      => 'ComputerGroupStatic',
                'itemtype'                  => self::getType(),
                'plugin_deploy_computers_groups_id' => $ID,
                'count'                     => count($rows),
                'entries'                   => $rows,
                'none_found'                => sprintf(__('No %s found', 'deploy'), self::getTypeName(Session::getPluralNumber())),
                'headings'                  => self::getheadings(),
            ]);
        }

        return true;
    }


    public static function install(Migration $migration)
    {
        global $DB;
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `plugin_deploy_computers_groups_id` int(11) NOT NULL DEFAULT '0',
                      `computers_id` int(11) NOT NULL DEFAULT '0',
                      PRIMARY KEY (`id`),
                      KEY `computers_id` (`computers_id`),
                      KEY `computergroups_id` (`plugin_deploy_computers_groups_id`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $DB->request($query) or die($DB->error());
        }
    }


    public static function uninstall(Migration $migration)
    {
        global $DB;
        $table = self::getTable();
        if ($DB->tableExists($table)) {
            $DB->request("DROP TABLE IF EXISTS `" . self::getTable() . "`") or die($DB->error());
        }
    }
}
