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

use CommonDBTM;
use CommonGLPI;
use Computer;
use Dropdown;
use Html;
use Migration;
use Search;
use Session;
use Toolbox;

class Computer_Group_Dynamic extends CommonDBTM
{
    public static $rightname  = 'computer_group';

    public static function getTypeName($nb = 0)
    {
        return _n('Dynamic groups', 'Dynamic group', $nb, 'deploy');
    }


    public static function canCreate()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }


    public static function canPurge()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }


    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {

        if (get_class($item) == ComputerGroup::getType()) {
            $count = 0;
            $computergroup_dynamic = new self();
            if ($computergroup_dynamic->getFromDBByCrit([
                'plugin_deploy_computers_groups_id' => $item->getID()
            ])) {
                $count = $computergroup_dynamic->countDynamicItems();
            }
            $ong = [];
            $ong[1] = self::createTabEntry(Self::getTypeName(Session::getPluralNumber()), $count);
            return $ong;
        }
        return '';
    }


    public static function getSpecificValueToDisplay($field, $values, array $options = [])
    {

        if (!is_array($values)) {
            $values = [$field => $values];
        }
        switch ($field) {
            case 'search':
                $count = 0;
                if (strpos($values['id'], Search::NULLVALUE) === false) {
                    $computergroup_dynamic = new Computer_Group_Dynamic();
                    $computergroup_dynamic->getFromDB($values['id']);
                    $count = $computergroup_dynamic->countDynamicItems();
                }
                return ($count) ? $count : ' 0 ';

            case '_virtual_dynamic_list':
                global $CFG_GLPI;
                $value = " ";
                $out = " ";
                if (strpos($values['id'], Search::NULLVALUE) === false) {
                    $computers_params = unserialize($values['search']);
                    $computers_params["reset"] = true;
                    $search_params = Search::manageParams('Computer', $computers_params);
                    $data = Search::prepareDatasForSearch('Computer', $search_params);
                    Search::constructSQL($data);
                    Search::constructData($data);

                    foreach ($data['data']['rows'] as $colvalue) {
                        $value .= "<a href='" . Computer::getFormURLWithID($colvalue['id']) . "'>";
                        $value .= Dropdown::getDropdownName('glpi_computers', $colvalue['id']) . "</a>" . Search::LBBR;
                    }
                }

                if (!preg_match('/' . Search::LBHR . '/', $value)) {
                    $values = preg_split('/' . Search::LBBR . '/i', $value);
                    $line_delimiter = '<br>';
                } else {
                    $values = preg_split('/' . Search::LBHR . '/i', $value);
                    $line_delimiter = '<hr>';
                }

                //move full list to tooltip if needed
                if (
                    count($values) > 1
                    && Toolbox::strlen($value) > $CFG_GLPI['cut']
                ) {
                    $value = '';
                    foreach ($values as $v) {
                        $value .= $v . $line_delimiter;
                    }
                    $value = preg_replace('/' . Search::LBBR . '/', '<br>', $value);
                    $value = preg_replace('/' . Search::LBHR . '/', '<hr>', $value);
                    $value = '<div class="fup-popup">' . $value . '</div>';
                    $valTip = "&nbsp;" . Html::showToolTip(
                        $value,
                        [
                            'awesome-class'   => 'fa-comments',
                            'display'         => false,
                            'autoclose'       => false,
                            'onclick'         => true
                        ]
                    );
                    $out .= $values[0] . $valTip;
                } else {
                    $value = preg_replace('/' . Search::LBBR . '/', '<br>', $value);
                    $value = preg_replace('/' . Search::LBHR . '/', '<hr>', $value);
                    $out .= $value;
                }
                return $out;
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


    public function countDynamicItems()
    {
        $count = 0;
        $computers_params = unserialize($this->fields['search']);
        $computers_params["reset"] = true;
        $search_params = Search::manageParams('Computer', $computers_params);
        $data = Search::prepareDatasForSearch('Computer', $search_params);
        Search::constructSQL($data);
        Search::constructData($data, true);
        $count = $data['data']['totalcount'];
        return $count;
    }

    public function isDynamicSearchMatchComputer(Computer $computer)
    {
        $count = 0;

        //add new criteria to force computer ID
        $search = unserialize($this->fields['search']);
        $search['criteria'][] = [
            "link" => "AND",
            "field" => 2, //computer ID
            "searchtype" => 'contains',
            "value" => $computer->fields['id'],
        ];

        $search["reset"] = true;
        $search_params = Search::manageParams('Computer', $search);
        $data = Search::prepareDatasForSearch('Computer', $search_params);
        Search::constructSQL($data);
        Search::constructData($data, true);
        $count = $data['data']['totalcount'];
        return $count;
    }


    public static function showForItem(ComputerGroup $computergroup)
    {

        $ID = $computergroup->getField('id');
        if (!$computergroup->can($ID, UPDATE)) {
            return false;
        }

        $canedit = $computergroup->canEdit($ID);
        if ($canedit) {

            $firsttime = true;
            //load dynamic search criteria from DB if exist
            $computergroup_dynamic = new self();
            if ($computergroup_dynamic->getFromDBByCrit([
                'plugin_deploy_computers_groups_id' => $ID
            ])) {
                $computers_params = unserialize($computergroup_dynamic->fields['search']);
                $computers_params["reset"] = true;
                $p = $search_params = Search::manageParams('Computer', $computers_params);
                $firsttime = false;
            } else {
                //retrieve filter value from search if exist and reset it
                $_GET["reset"] = true;
                $p = $search_params = Search::manageParams('Computer', $_GET);
                if (isset($_SESSION['glpisearch']['Computer'])) {
                    unset($_SESSION['glpisearch']['Computer']);
                }
            }

            //redirect to computergroup dynamic tab after saved search
            $target = ComputerGroup::getFormURLWithID($ID);
            $target .= "&_glpi_tab=Computer_Group_Dynamic$1";
            $p['target'] = $target;
            $p['addhidden'] = [
                'plugin_deploy_computers_groups_id' => $computergroup->getID(),
                'id'                                         => $computergroup->getID(),
                'start'                                      => 0
            ];
            $p['actionname']   = 'save';
            $p['actionvalue']  = _sx('button', 'Save');
            $p['showbookmark'] = false;
            Search::showGenericSearch(Computer::getType(), $p);

            //display result from search
            if (!$firsttime) {
                $data = Search::prepareDatasForSearch('Computer', $search_params);
                Search::constructSQL($data);
                Search::constructData($data);
                $data['search']['target'] = $target;
                $data['search']['showmassiveactions'] = false;
                $data['search']['is_deleted'] = false;
                Search::displayData($data);

                //remove search header(trashbin / map switch)
                echo Html::scriptBlock("
               $(document).ready(
                  function() {
                     $('div.search-header').remove();
                  }
               );
            ");
            }
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
                      `search` text,
                      PRIMARY KEY (`id`),
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
