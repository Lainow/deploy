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
use DBConnection;
use Migration;

class Package_UserInteraction extends CommonDBTM
{
    use Package_Subitem;

    public static $rightname = 'entity';

    private const SUBITEM_TYPE = 'useralert';

    public const BEFORE_DOWLOAD      = "before";
    public const AFTER_DOWLOAD       = "after_download";
    public const AFTER_ACTIONS       = "after";
    public const AFTER_DOWNLOAD_FAIL = "after_download_failure";
    public const AFTER_ACTION_FAIL   = "after_failure";

    public const IT_OK                  = "ok";
    public const IT_OK_ASYNC            = "ok_sync";
    public const IT_OK_CANCEL           = "okcancel";
    public const IT_YES_NO              = "yesno";
    public const IT_ABORT_RETRY_IGNORE  = "abortretryignore";
    public const IT_RETRY_CANCEL        = "retrycancel";
    public const IT_CANCEL_TRY_CONTINUE = "canceltrycontinue";
    public const IT_YES_NO_CANCEL       = "yesnocancel";

    public const ICON_NONE     = "none";
    public const ICON_WARNING  = "warn";
    public const ICON_INFO     = "info";
    public const ICON_ERROR    = "error";
    public const ICON_QUESTION = "question";

    public static function getTypeName($nb = 0)
    {
        return _n('Alert', 'Alerts', $nb, 'deploy');
    }


    public static function getIcon()
    {
        return 'ti ti-message-report';
    }


    private static function getheadings(): array
    {
        return [
            'name' => __('Label', 'deploy'),
        ];
    }


    public static function getTypes(bool $with_icon = false): array
    {
        return [
            SELF::BEFORE_DOWLOAD      => ($with_icon ? '<i class="fa-fw me-1 fas fa-cloud-arrow-down"></i>' : "")
                                   . __('Before download', 'deploy'),
            SELF::AFTER_DOWLOAD       => ($with_icon ? '<i class="fa-fw me-1 fas fa-desktop-arrow-down' : "")
                                   . __('After download', 'deploy'),
            SELF::AFTER_ACTIONS       => ($with_icon ? '<i class="fa-fw me-1 fas fa-folder-gear"></i>' : "")
                                   . __('After actions execution', 'deploy'),
            SELF::AFTER_DOWNLOAD_FAIL => ($with_icon ? '<i class="fa-fw me-1 fas fa-cloud-exclamation"></i>' : "")
                                   . __('On download failure', 'deploy'),
            SELF::AFTER_ACTION_FAIL   => ($with_icon ? '<i class="fa-fw me-1 fas fa-folder-xmark"></i>' : "")
                                   . __('On actions failure', 'deploy'),
        ];
    }


    public static function getInteractionTypes(): array
    {
        return [
            self::IT_OK                  => __('OK', 'deploy'),
            self::IT_OK_ASYNC            => __('OK (Asynchronous)', 'deploy'),
            self::IT_OK_CANCEL           => __('OK - Cancel', 'deploy'),
            self::IT_YES_NO              => __('Yes - No', 'deploy'),
            self::IT_YES_NO_CANCEL       => __('Yes - No - Cancel', 'deploy'),
            self::IT_ABORT_RETRY_IGNORE  => __('Abort - Retry - Ignore', 'deploy'),
            self::IT_RETRY_CANCEL        => __('Retry - Cancel', 'deploy'),
            self::IT_CANCEL_TRY_CONTINUE => __('Cancel - Try - Continue', 'deploy'),
        ];
    }


    public static function getIcons(bool $with_icon = false): array
    {
        return [
            self::ICON_NONE     => __('None', 'deploy'),
            self::ICON_WARNING  => ($with_icon ? '<i class="fa-fw me-1 text-warning ti ti-alert-triangle"></i>' : "")
                                   . __('Warning', 'deploy'),
            self::ICON_INFO     => ($with_icon ? '<i class="fa-fw me-1 text-info ti ti-info-circle"></i>' : "")
                                   . __('Information', 'deploy'),
            self::ICON_ERROR    => ($with_icon ? '<i class="fa-fw me-1 text-danger ti ti-alert-octagon"></i>' : "")
                                   . __('Error', 'deploy'),
            self::ICON_QUESTION => ($with_icon ? '<i class="fa-fw me-1 ti ti-question-mark"></i>' : "")
                                   . __('Question', 'deploy'),
        ];
    }


    public static function getLabelForType(string $type = null, bool $with_icon = false): string
    {
        $types = self::getTypes($with_icon);
        return $types[$type] ?? "";
    }


    public function prepareInputForAdd($input)
    {
        $input["order"] = $input['order'] ?? $this->getNextOrder();

        return $input;
    }


    public static function getFormattedArrayForPackage(Package $package): array
    {
        $alerts = [];

        return $alerts;
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
                `plugin_deploy_packages_id` int $sign NOT NULL DEFAULT '0',
                `name` varchar(255) DEFAULT NULL,
                `title` varchar(255) DEFAULT NULL,
                `text` text,
                `type` varchar(50) DEFAULT NULL,
                `icon` varchar(10) DEFAULT NULL,
                `order` smallint unsigned NOT NULL DEFAULT '0',
                `date_creation` timestamp NULL DEFAULT NULL,
                `date_mod` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `plugin_deploy_packages_id` (`plugin_deploy_packages_id`),
                KEY `date_creation` (`date_creation`),
                KEY `date_mod` (`date_mod`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
            $DB->query($query) or die($DB->error());
        }
    }
}