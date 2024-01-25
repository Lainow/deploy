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

use CommonGLPI;
use DbUtils;
use Html;
use Migration;
use Profile as GlobalProfile;
use ProfileRight;

class Profile extends GlobalProfile {

   public static $rightname = 'profile';

   static function getTypeName($nb = 0) {
      return __('Deploy', 'deploy');
   }


   static function getAllRights($all = false) {
      $rights = [
         ['itemtype' => ComputerGroup::getType(),
               'label'    => ComputerGroup::getTypeName(),
               'field'    => 'computer_group'
         ]
      ];
      return $rights;
   }

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {

      if ($item->getType() == GlobalProfile::class) {
         return self::createTabEntry(self::getTypeName());
      }
      return '';
   }


   static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0) {

      if ($item instanceof GlobalProfile
          && $item->getField('id')) {
         return self::showForProfile($item->getID());
      }
      return true;
   }


   static function showForProfile($profiles_id = 0) {
      $canupdate = self::canUpdate();
      $profile = new GlobalProfile();
      $profile->getFromDB($profiles_id);
      echo "<div class='firstbloc'>";
      echo "<form method='post' action='".$profile->getFormURL()."'>";

      $rights = self::getAllRights();
      $profile->displayRightsChoiceMatrix($rights, array(
         'canedit'       => $canupdate,
         'title'         => self::getTypeName(),
      ));

      if ($canupdate) {
         echo "<div class='center'>";
         echo Html::hidden('id', array('value' => $profiles_id));
         echo Html::submit(_sx('button', 'Save'), array('name' => 'update'));
         echo "</div>\n";
         Html::closeForm();

         echo "</div>";
      }
   }


   /**
    * @param $ID
    */
    static function createFirstAccess($ID) {
      self::addDefaultProfileInfos($ID, ['computer_group' => PURGE + CREATE + UPDATE + READ ], true);
   }

   /**
    * @param      $profiles_id
    * @param      $rights
    * @param bool $drop_existing
    *
    * @internal param $profile
    */
   static function addDefaultProfileInfos($profiles_id, $rights, $drop_existing = false) {

      $profileRight = new ProfileRight();
      $dbu = new DbUtils();
      foreach ($rights as $right => $value) {
         if ($dbu->countElementsInTable('glpi_profilerights',
                                        ["profiles_id" => $profiles_id, "name" => $right]) && $drop_existing) {
            $profileRight->deleteByCriteria(['profiles_id' => $profiles_id, 'name' => $right]);
         }
         if (!$dbu->countElementsInTable('glpi_profilerights',
                                         ["profiles_id" => $profiles_id, "name" => $right])) {
            $plugin_right['profiles_id'] = $profiles_id;
            $plugin_right['name']        = $right;
            $plugin_right['rights']      = $value;
            $profileRight->add($plugin_right);

            //Add right to the current session
            $_SESSION['glpiactiveprofile'][$right] = $value;
         }
      }
   }

   public static function install(Migration $migration) { 
      foreach (Profile::getAllRights() as $right) {
         ProfileRight::addProfileRights([$right['field']]);
      }
      self::createFirstAccess($_SESSION['glpiactiveprofile']['id']);
      return true;
   }

   public static function uninstall(Migration $migration) {
      foreach (Profile::getAllRights() as $right) {
         ProfileRight::deleteProfileRights([$right['field']]);
      }
   }
}
