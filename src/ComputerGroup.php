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

use CommonDBTM;
use Computer;
use DisplayPreference;
use Glpi\Application\View\TemplateRenderer;
use Html;
use Migration;
use Search;
use Session;

class ComputerGroup extends CommonDBTM
{

    public $dohistory  = true;
    public static $rightname = 'computer_group';


    public static function getTypeName($nb = 0)
    {
        return _n('Computer Group', 'Computers Group', $nb, 'deploy');
    }

    public static function canCreate()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public static function canPurge()
    {
        return Session::haveRight(static::$rightname, UPDATE);
    }

    public function defineTabs($options = [])
    {
        $ong = [];
        $this->addDefaultFormTab($ong)
            ->addStandardTab('GlpiPlugin\Deploy\Computer_Group_Dynamic', $ong, $options)
            ->addStandardTab('GlpiPlugin\Deploy\Computer_Group_Static', $ong, $options)
            ->addStandardTab('Log', $ong, $options);
        return $ong;
    }

    public function rawSearchOptions()
    {
        $tab = parent::rawSearchOptions();

        $tab[] = [
            'id'                 => '2',
            'table'              => $this->getTable(),
            'field'              => 'id',
            'name'               => __('ID'),
            'massiveaction'      => false, // implicit field is id
            'datatype'           => 'number'
        ];

        $tab[] = [
            'id'                 => '3',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => __('Comment'),
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'               => '5',
            'table'            => Computer_Group_Dynamic::getTable(),
            'field'            => 'search',
            'name'             => __('Number of dynamics items', 'deploy'),
            'nosearch'         => true,
            'massiveaction'    => false,
            'forcegroupby'     => true,
            'additionalfields' => ['id'],
            'joinparams'       => ['jointype' => 'child'],
            'datatype'         => 'specific',
        ];

        $tab[] = [
            'id'                 => '6',
            'table'              => Computer_Group_Static::getTable(),
            'field'              => 'id',
            'name'               => __('Number of statics items', 'deploy'),
            'forcegroupby'       => true,
            'usehaving'          => true,
            'nosearch'           => true,
            'datatype'           => 'count',
            'massiveaction'      => false,
            'joinparams'       => ['jointype' => 'child'],
        ];

        $tab[] = [
            'id'               => '7',
            'table'            => Computer_Group_Dynamic::getTable(),
            'field'            => '_virtual_dynamic_list',
            'name'             => __('List of dynamics items', 'deploy'),
            'massiveaction'    => false,
            'forcegroupby'     => true,
            'nosearch'         => true,
            'additionalfields' => ['id', 'search'],
            'searchtype'       => ['equals', 'notequals'],
            'joinparams'       => ['jointype' => 'child'],
            'datatype'         => 'specific',
        ];

        $tab[] = [
            'id'                 => '8',
            'table'              => Computer::getTable(),
            'field'              => 'name',
            'datatype'           => 'itemlink',
            'name'               => __('List of statics items', 'deploy'),
            'forcegroupby'       => true,
            'massiveaction'      => false,
            'joinparams'         => [
                'beforejoin'         => [
                    'table'              => Computer_Group_Static::getTable(),
                    'joinparams'         => [
                        'jointype'           => 'child',
                    ]
                ]
            ]
        ];

        return $tab;
    }


    public function showForm($id, array $options = [])
    {
        if (!empty($id)) {
            $this->getFromDB($id);
        } else {
            $this->getEmpty();
        }
        $this->initForm($id, $options);

        TemplateRenderer::getInstance()->display('generic_show_form.html.twig', [
            'item'         => $this,
            'params'       => $options,
        ]);
        return true;
    }

    public function countDynamicItem()
    {
        global $DB;
        $count = 0;

        $params = [
            'SELECT' => '*',
            'FROM'   => Computer_Group_Dynamic::getTable(),
            'WHERE'  => ['plugin_deploy_computers_groups_id' => $this->fields['id']],
        ];

        $iterator = $DB->request($params);
        foreach ($iterator as $computergroup_dynamic) {
            $params = unserialize($computergroup_dynamic['search']);
            $computers_params["reset"] = true;
            $search_params = Search::manageParams('Computer', $computers_params);
            $data = Search::prepareDatasForSearch('Computer', $search_params);
            Search::constructSQL($data);
            Search::constructData($data, true);
            $count += $data['data']['totalcount'];
        }

        return $count;
    }


    public function countStaticItem()
    {
        global $DB;
        $count = 0;

        $params = [
            'SELECT' => '*',
            'FROM'   => Computer_Group_Static::getTable(),
            'WHERE'  => ['plugin_deploy_computers_groups_id' => $this->fields['id']],
        ];

        $iterator = $DB->request($params);
        $count = count($iterator);

        return $count;
    }


    public static function install(Migration $migration)
    {
        global $DB;
        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");
            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `comment` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                      `date_creation` timestamp NULL DEFAULT NULL,
                      `date_mod` timestamp NULL DEFAULT NULL,
                      PRIMARY KEY (`id`),
                      KEY `name` (`name`),
                      KEY `comment` (`comment`),
                      KEY `date_creation` (`date_creation`),
                      KEY `date_mod` (`date_mod`)
                      ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
            $DB->request($query) or die($DB->error());

            // install default display preferences
            $dpreferences = new DisplayPreference;
            $found_dpref = $dpreferences->find(['itemtype' => 'GlpiPlugin\\Deploy\\ComputerGroup']);
            if (count($found_dpref) == 0) {
                $DB->request("INSERT INTO `glpi_displaypreferences`
                           (`itemtype`, `num`, `rank`, `users_id`)
                        VALUES
                           ('GlpiPlugin\\Deploy\\ComputerGroup', 3, 1, 0),
                           ('GlpiPlugin\\Deploy\\ComputerGroup', 5, 2, 0),
                           ('GlpiPlugin\\Deploy\\ComputerGroup', 6, 3, 0)");
            }
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


    public static function getIcon()
    {
        return "fa-fw ti ti-device-laptop";
    }


    public function post_purgeItem()
    {
    }
}
