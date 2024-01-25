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
use Migration;
use DBConnection;
use DisplayPreference;
use Entity;
use Glpi\Application\View\TemplateRenderer;

class Package extends CommonDBTM
{
    public static $rightname = 'entity';

    public const MOVE_BEFORE = 'before';
    public const MOVE_AFTER = 'after';

    public static function getTypeName($nb = 0)
    {
        return _n('Package', 'Packages', $nb, 'deploy');
    }

    public static function getIcon()
    {
        return 'ti ti-file-zip';
    }

    public function defineTabs($options = [])
    {

        $ong = [];
        $this->addDefaultFormTab($ong)
            ->addStandardTab(Package_Check::getType(), $ong, $options)
            ->addStandardTab(Package_File::getType(), $ong, $options)
            ->addStandardTab(Package_Action::getType(), $ong, $options)
            ->addStandardTab(Package_UserInteraction::getType(), $ong, $options)
            ->addStandardTab(Task_Package::getType(), $ong, $options)
            ->addStandardTab(__CLASS__, $ong, $options);

        return $ong;
    }

    public function showDebug()
    {
        TemplateRenderer::getInstance()->display('@deploy/package/debug_json.html.twig', [
            'json' => self::getJson($this, true)
        ]);
    }


    public static function getJson(Package $package, bool $pretty_json = false): string
    {
        $checks           = Package_Check::getFormattedArrayForPackage($package);
        $files            = Package_File::getFormattedArrayForPackage($package);
        $actions          = Package_Action::getFormattedArrayForPackage($package);
        $userinteractions = Package_UserInteraction::getFormattedArrayForPackage($package);

        $json_array = [
            'jobs' => [
                'checks'           => $checks,
                'associatedFiles'  => array_keys($files),
                'actions'          => $actions,
                'userinteractions' => $userinteractions
            ],
            'associatedFiles' => $files,
        ];

        return json_encode($json_array, $pretty_json ? (JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : 0);
    }


    public static function moveSubitem(
        string $subitem_itemtype,
        int $ID,
        int $ref_ID,
        string $type = self::MOVE_AFTER
    ): bool
    {
        global $DB;

        $used_traits = class_uses($subitem_itemtype);
        if (!in_array("Package_Subitem", $used_traits)) {
            return false;
        }

        $subitem = new $subitem_itemtype();
        $subitem->getFromDB($ID);
        $old_rank   = $subitem->fields['order'];
        $package_id = $subitem->fields['plugin_deploy_packages_id'];

        // Compute new ranking
        if ($ref_ID) { // Move after/before an existing line
            $subitem->getFromDB($ref_ID);
            $new_rank = $subitem->fields["order"];
        } else if ($type == self::MOVE_AFTER) {
            // Move after all
            $result = $DB->request([
                'SELECT' => ['MAX' => 'order AS maxi'],
                'FROM'   => $subitem::getTable(),
                'WHERE'  => ['plugin_deploy_packages_id' => $package_id]
            ])->current();
            $new_rank = $result['maxi'];
        } else {
            // Move before all
            $new_rank = 1;
        }

        $result = false;

        // Move others lines in the collection
        if ($old_rank < $new_rank) {
            if ($type == self::MOVE_BEFORE) {
                $new_rank--;
            }

            // Move back all lines between old and new rank
            $iterator = $DB->request([
                'SELECT' => ['id', 'order'],
                'FROM'   => $subitem::getTable(),
                'WHERE'  => [
                    'plugin_deploy_packages_id' => $package_id,
                    ['order' => ['>', $old_rank]],
                    ['order' => ['<=', $new_rank]]
                ]
            ]);
            foreach ($iterator as $data) {
                $data['order']--;
                $result = $subitem->update($data);
            }
        } else if ($old_rank > $new_rank) {
            if ($type == self::MOVE_AFTER) {
                $new_rank++;
            }

            // Move forward all lines between old and new rank
            $iterator = $DB->request([
                'SELECT' => ['id', 'order'],
                'FROM'   => $subitem::getTable(),
                'WHERE'  => [
                    'plugin_deploy_packages_id' => $package_id,
                    ['order' => ['>=', $new_rank]],
                    ['order' => ['<', $old_rank]]
                ]
            ]);
            foreach ($iterator as $data) {
                $data['order']++;
                $result = $subitem->update($data);
            }
        } else { // $old_rank == $new_rank : nothing to do
            $result = false;
        }

        // Move the line
        if ($result && ($old_rank != $new_rank)) {
            $result = $subitem->update([
                'id'    => $ID,
                'order' => $new_rank
            ]);
        }
        return ($result ? true : false);
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
            'id'                 => '16',
            'table'              => $this->getTable(),
            'field'              => 'comment',
            'name'               => __('Comments'),
            'datatype'           => 'text'
        ];

        $tab[] = [
            'id'                 => '19',
            'table'              => $this->getTable(),
            'field'              => 'date_mod',
            'name'               => __('Last update'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '121',
            'table'              => $this->getTable(),
            'field'              => 'date_creation',
            'name'               => __('Creation date'),
            'datatype'           => 'datetime',
            'massiveaction'      => false
        ];

        $tab[] = [
            'id'                 => '80',
            'table'              => 'glpi_entities',
            'field'              => 'completename',
            'name'               => Entity::getTypeName(1),
            'datatype'           => 'dropdown'
        ];

        return $tab;
    }


    public function cleanDBonPurge()
    {
        $this->deleteChildrenAndRelationsFromDb(
            [
                Task_Package::class,
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
            $DB->request($query) or die($DB->error());
        }

        // add display preferences
        $nb_display_pref = countElementsInTable(DisplayPreference::getTable(), [
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
        }
    }


    public static function uninstall(Migration $migration)
    {
        global $DB;

        $table = self::getTable();
        $migration->displayMessage("Uninstalling $table");
        $migration->dropTable($table);

        $DB->request("DELETE FROM `glpi_displaypreferences` WHERE `itemtype` = '" . self::getType() . "'");
    }
}
