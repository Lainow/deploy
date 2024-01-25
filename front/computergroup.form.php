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

use Glpi\Event;
use GlpiPlugin\Deploy\ComputerGroup;
use GlpiPlugin\Deploy\Computer_Group_Dynamic;
use GlpiPlugin\Deploy\Computer_Group_Static;

include ('../../../inc/includes.php');

Session::checkRight("computer_group", READ);

if (!isset($_GET["id"])) {
   $_GET["id"] = "";
}

if (!isset($_GET["withtemplate"])) {
   $_GET["withtemplate"] = "";
}

$computergroup = new ComputerGroup();
$computergroupstatic = new Computer_Group_Static();
$computergroup_dynamic = new Computer_Group_Dynamic();

//Add a new computergroup
if (isset($_POST["add"])) {
   $computergroup->check(-1, CREATE, $_POST);
   if ($newID = $computergroup->add($_POST)) {
      Event::log($newID, "ComputerGroup", 4, "inventory",
                 sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $_POST["name"]));

      if ($_SESSION['glpibackcreated']) {
         Html::redirect($computergroup->getLinkURL());
      }
   }
   Html::back();

   // purge a computergroup
}if (isset($_POST["add_staticcomputer"])) {

   if (!$_POST['computers_id']){
      Session::addMessageAfterRedirect(__('Please select a computer'), false, ERROR);
      Html::back();
   }

   $computergroupstatic->check(-1, CREATE, $_POST);
   if ($newID = $computergroupstatic->add($_POST)) {
      Event::log($newID, "Computer_Group_Static", 4, "inventory",
                 sprintf(__('%1$s adds the item %2$s'), $_SESSION["glpiname"], $computergroupstatic::getTypeName(0)));

      if ($_SESSION['glpibackcreated']) {
         $computergroup->getFromDB($_POST['plugin_deploy_computers_groups_id']);
         Html::redirect($computergroup->getLinkURL());
      }
   }
   Html::back();

   // purge a computergroup
} else if (isset($_POST["purge"])) {
   $computergroup->check($_POST['id'], PURGE);
   if ($computergroup->delete($_POST, 1)) {
      Event::log($_POST["id"], "ComputerGroup", 4, "inventory",
                 //TRANS: %s is the user login
                 sprintf(__('%s purges an item'), $_SESSION["glpiname"]));
   }
   $computergroup->redirectToList();

   //update a computergroup
} else if (isset($_POST["update"])) {
   $computergroup->check($_POST['id'], UPDATE);
   $computergroup->update($_POST);
   Event::log($_POST["id"], "ComputerGroup", 4, "inventory",
              //TRANS: %s is the user login
              sprintf(__('%s updates an item'), $_SESSION["glpiname"]));
   Html::back();

} else {//print computergroup information
   //save search parameters for dynamic group
   if (isset($_GET["save"])) {
      $input = ['plugin_deploy_computers_groups_id' => $_GET['plugin_deploy_computers_groups_id']];
      $search = serialize(['is_deleted' => isset($_GET['is_deleted']) ? $_GET['is_deleted'] : 0 ,
                           'as_map' =>  isset($_GET['as_map']) ? $_GET['as_map'] : 0,
                           'criteria'     => $_GET['criteria'],
                           'metacriteria' => isset($_GET['metacriteria']) ? $_GET['metacriteria'] : []]);

      if (!$computergroup_dynamic->getFromDBByCrit($input)) {
         $input['search'] = $search;
         $computergroup_dynamic->add($input);
      } else  {
         $input = $computergroup_dynamic->fields;
         $input['search'] = $search;
         $computergroup_dynamic->update($input);
      }
   }else if (isset($_GET["reset"])) {
      $computergroup_dynamic->deleteByCriteria(["plugin_deploy_computers_groups_id" => $_GET['id']]);
   }


   Html::header(
      ComputerGroup::getTypeName(Session::getPluralNumber()),
      '',
      'tools',
      'glpiplugin\deploy\menu',
      'computer_group'
  );

   //show computergroup form to add
   $computergroup->display([
      'id' => (int) $_GET["id"],
   ]);

   Html::footer();
}
