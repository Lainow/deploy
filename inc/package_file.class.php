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

class PluginDeployPackage_File extends CommonDBTM
{
    use PluginDeployPackage_Subitem;

    public static $rightname = 'entity';
    private const SUBITEM_TYPE = 'file';

    public static function getTypeName($nb = 0)
    {
        return _n('File', 'Files', $nb, 'deploy');
    }


    public static function getIcon()
    {
        return 'ti ti-file';
    }


    private static function getheadings(): array
    {
        return [
            'filename'           => __('filename', 'deploy'),
            'size'               => __('size', 'deploy'),
            'mimetype'           => __('mimetype', 'deploy'),
            'p2p'                => __('P2P', 'deploy'),
            'p2p_retention_days' => __('P2P Retention day', 'deploy'),
            'uncompress'         => __('Uncompress', 'deploy'),
            'sha512'             => __('SHA', 'deploy'),
        ];
    }


    public function prepareInputForAdd($input)
    {
        $repository = new PluginDeployRepository;
        switch ($input['upload_mode'])
        {
            case "from_computer":
                $r_file = $repository->AddFileFromComputer();
                $input  = array_merge($input, $r_file->getDefinition());

                break;
            case "from_server":
                $r_file = $repository->addFileFromServer($input['server_file']);
                $input  = array_merge($input, $r_file->getDefinition());
                break;
        }

        if (!isset($input['filename']) || strlen($input['filename']) == 0) {
            return false;
        }

        return $input;
    }


    public function pre_deleteItem()
    {
        $found_files = $this->find([
            'sha512' => $this->fields['sha512']
        ]);

        // do not delete file in repository if it's also used in other packages
        if (count($found_files) === 1) {
            $repository = new PluginDeployRepository;
            $repository->deleteFile($this->fields['sha512']);
        }

        return true;
    }


    public static function getFilesTreeFromServer(): string
    {
        $path = GLPI_UPLOAD_DIR;

        $dir_iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        $dom = new DomDocument("1.0");
        $list = $dom->createElement("ul");
        $list->setAttribute('id', "treeData");
        $dom->appendChild($list);
        $node = $list;
        $depth = 0;
        $id = 1;
        foreach ($dir_iterator as $object) {
            $rel_path = str_replace($path, '', $object->getPathname());
            if ($dir_iterator->getDepth() == $depth) {
                //the depth hasnt changed so just add another li
                $li = $dom->createElement('li', $object->getFilename());
                $li->setAttribute('id', $id);
                $li->setAttribute('data-json', '{"path": "'.$rel_path.'"}');
                if ($object->isDir()) {
                    $li->setAttribute('class', 'folder');
                }
                $node->appendChild($li);
            }
            elseif ($dir_iterator->getDepth() > $depth) {
                //the depth increased, the last li is a non-empty folder
                $li = $node->lastChild;
                $ul = $dom->createElement('ul');
                $li->appendChild($ul);
                $li->setAttribute('id', $id);
                $li->setAttribute('class', 'folder unselectable');
                $new_li = $dom->createElement('li', $object->getFilename());
                $new_li->setAttribute('data-json', '{"path": "'.$rel_path.'"}');
                $ul->appendChild($new_li);
                $node = $ul;
            }
            else{
                //the depth decreased, going up $difference directories
                $difference = $depth - $dir_iterator->getDepth();
                for ($i = 0; $i < $difference; $difference--) {
                    $node = $node->parentNode->parentNode;
                }
                $li = $dom->createElement('li', $object->getFilename());
                $li->setAttribute('data-json', '{"path": "'.$rel_path.'"}');
                $li->setAttribute('id', $id);
                if ($object->isDir()) {
                    $li->setAttribute('class', 'folder');
                }
                $node->appendChild($li);
            }
            $depth = $dir_iterator->getDepth();

            $id++;
        }

        return $dom->saveHtml();
    }


    public static function install(Migration $migration)
    {
        global $DB;

        $table = self::getTable();
        if (!$DB->tableExists($table)) {
            $migration->displayMessage("Installing $table");

            $default_charset = DBConnection::getDefaultCharset();
            $default_collation = DBConnection::getDefaultCollation();

            $query = "CREATE TABLE IF NOT EXISTS `$table` (
                `id` int NOT NULL AUTO_INCREMENT,
                `plugin_deploy_packages_id` int unsigned NOT NULL DEFAULT '0',
                `filename` text,
                `filesize` varchar(255) DEFAULT NULL,
                `mimetype` varchar(255) DEFAULT NULL,
                `sha512` varchar(128) DEFAULT NULL,
                `p2p` tinyint(1) NOT NULL DEFAULT '0',
                `p2p_retention_days` int(11) NOT NULL DEFAULT '0',
                `uncompress` tinyint(1) NOT NULL DEFAULT '0',
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