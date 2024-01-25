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

use \Session;

class Menu
{
    public static function getMenuName()
    {
        return __('Deploy');
    }


    public static function getIcon()
    {
        return 'ti ti-file-upload';
    }


    static function getMenuContent()
    {
        $menu = [];

        if (Task::canUpdate()) {
            $links_class = [
                Task::class,
                Package::class,
            ];

            if (ComputerGroup::canCreate()) {
                $links_class[] = ComputerGroup::class;
            }

            $links = [];
            foreach ($links_class as $link) {
                $link_text = "<span class='d-none d-xxl-block'>" . $link::getTypeName(Session::getPluralNumber()) . "</span>";
                $links["<i class='" . $link::getIcon() . "'></i>$link_text"] = $link::getSearchURL(false);
            }

            $menu = [
                'title'   => self::getMenuName(),
                'page'    => Task::getSearchURL(false),
                'icon'    => self::getIcon(),
                'options' => [],
                'links'   => $links,
            ];

            $menu['options']['package'] = [
                'title' => Package::getTypeName(Session::getPluralNumber()),
                'page'  => Package::getSearchURL(false),
                'icon'  => Package::getIcon(),
                'links' => $links,
            ];

            if (Package::canCreate()) {
                $add_link = Package::getFormURL(false);
                $menu['links']['add'] = $add_link;
                $menu['options']['package']['links']['add'] = $add_link;
            }

            $menu['options']['task'] = [
                'title' => Task::getTypeName(Session::getPluralNumber()),
                'page'  => Task::getSearchURL(false),
                'icon'  => Task::getIcon(),
                'links' => $links
            ];

            if (Task::canCreate()) {
                $menu['options']['task']['options']['add'] = Task::getFormURL(false);
                $menu['options']['task']['links']['add']   = Task::getFormURL(false);
            }

            if (ComputerGroup::canCreate()) {
                $menu['options']['computer_group'] = [
                    'title' => ComputerGroup::getTypeName(Session::getPluralNumber()),
                    'page'  => ComputerGroup::getSearchURL(false),
                    'icon'  => ComputerGroup::getIcon(),
                    'links' => $links,
                ];

                $add_link = ComputerGroup::getFormURL(false);
                $menu['options']['computer_group']['options']['add'] = ComputerGroup::getFormURL(false);
                $menu['options']['computer_group']['links']['add']   = ComputerGroup::getFormURL(false);
            }
        }

        if (count($menu)) {
            return $menu;
        }

        return false;
    }
}
