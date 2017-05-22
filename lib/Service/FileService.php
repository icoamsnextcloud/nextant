<?php

/**
 * Nextcloud - nextant
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Maxence Lange <maxence@pontapreta.net>
 * @copyright Maxence Lange 2016
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OCA\Nextant\Service;

use OC\User\User;
use OCA\Nextant\Db\ExclusionList;
use \OCA\Nextant\Service\SolrService;
use \OCA\Nextant\Service\SolrToolsService;
use \OCA\Nextant\Items\ItemError;
use \OCA\Nextant\Items\ItemDocument;
use OC\Files\Filesystem;
use OC\Files\View;
use OC\Share\Share;
use OCP\Files\NotFoundException;
use OCP\Files\StorageNotAvailableException;

class FileService
{

    const UPDATE_MAXIMUM_FILES = 1000;

    const NOINDEX_FILE = '.noindex';
    
    // private $root;
    private $configService;

    private $rootFolder;

    private $solrService;

    private $solrTools;

    private $miscService;

    private $view;

    private $userId;

    private $externalMountPoint;

    private $exclusionListMapper;

    private $oleExclusionObject = array(
        "ObjectPool",
        "Ole10Native",
        ""
    );

    public function __construct($configService, $rootFolder, $solrService, $solrTools, $miscService, $exclusionListMapper)
    {
        // $this->root = $root;
        $this->configService = $configService;
        $this->rootFolder = $rootFolder;
        $this->solrService = $solrService;
        $this->solrTools = $solrTools;
        $this->miscService = $miscService;
        /*
         * Author: Lawrence Chan
         * Description:
         * */
        $this->exclusionListMapper = $exclusionListMapper;
    }

    public function setDebug($debug)
    {
        $this->miscService->setDebug($debug);
        $this->solrService->setDebug($debug);
        $this->solrTools->setDebug($debug);
    }

    public function configured()
    {
        if (! \OCP\App::isEnabled('files'))
            return false;
        
        if ($this->configService->getAppValue('index_files') == 1)
            return true;
        
        return false;
    }

    public static function getId($path)
    {
        $fileId = 0;
        $info = Filesystem::getFileInfo($path);
        if ($info !== false)
            $fileId = (int) $info['fileid'];
        
        return $fileId;
    }

    public static function getFileInfo($pathorid)
    {
        try {
            $view = Filesystem::getView();
            if (intval($pathorid) != 0)
                $path = $view->getPath($pathorid);
            else
                $path = $pathorid;
            
            return $view->getFileInfo($path);
        } catch (NotFoundException $e) {
            return false;
        }
    }

    public static function getUserFolder($rootFolder, $userId, $path)
    {
        \OC\Files\Filesystem::initMountPoints($userId);
        $dir = '/' . $userId;
        $folder = null;
        
        try {
            return $rootFolder->get($dir)->get($path);
        } catch (NotFoundException $e) {}
        
        return false;
    }

    public static function getBaseTypeFromMime($mimetype)
    {
        return substr($mimetype, 0, strpos($mimetype, '/'));
    }

    public function initUser($userId, $complete = false)
    {
        $this->userId = $userId;
        Filesystem::init($this->userId, '');
        $this->view = Filesystem::getView();
        
        if ($complete)
            $this->initUserExternalMountPoints();
    }

    public function endUser()
    {
        $this->view = null;
        $this->userId = '';
        // $this->externalMountPoint = array();
    }

    private function initUserExternalMountPoints()
    {
        if ($this->configService->getAppValue('index_files_external') !== '1')
            return false;
        
        if (! \OCP\App::isEnabled('files_external'))
            return false;
        
        $data = array();
        $mounts = \OC_Mount_Config::getAbsoluteMountPoints($this->userId);
        foreach ($mounts as $mountPoint => $mount) {
            $data[] = array(
                'id' => $mount['id'],
                'path' => $mountPoint,
                'shares' => $mount['applicable'],
                'personal' => $mount['personal']
            );
        }
        
        $this->externalMountPoint = $data;
    }

    /**
     * add some information to the itemDocument
     *
     * @param ItemDocument $item            
     * @return boolean
     */
    public function syncDocument(&$item)
    {
        $item->synced(true);
        
        // $this->miscService->log('-- local: ' . (($item->istorageLocal()
        // ) ? 'y' : 'n') . ' -- external: ' . (($item->isExternal()) ? 'y' : 'n') . ' -- encrypted:' . (($item->isEncrypted()) ? 'y' : 'n') . ' -- test: ' . (($item->isTest()) ? 'y' : 'n') . ' -- ' . $item->getPath());
        
        if ($item->isFederated() && $this->configService->getAppValue('index_files_federated') !== '1')
            return false;
        
        if ($item->isExternal() && $this->configService->getAppValue('index_files_external') !== '1')
            return false;
        
        if ($item->isEncrypted() && $this->configService->getAppValue('index_files_encrypted') !== '1')
            return false;
        
        $size = round($item->getSize() / 1024 / 1024, 1);
        if ($size > $this->configService->getAppValue('index_files_max_size')) {
            $this->miscService->debug('File is too big (' . $size . ' > ' . $this->configService->getAppValue('index_files_max_size') . ')');
            return false;
        }
        
        // $this->miscService->log('__' . $item->getPath() . ' __ ' . $item->getId() . ' ___ ' . $item->getMimeType());
        if (! $this->solrService->extractableFile($item->getMimeType(), $item->getPath())) {
            $item->extractable(false);
            
            if ($this->configService->getAppValue('index_files_tree') === '1')
                $item->valid(true);
        } else {
            $item->valid(true);
            $item->extractable(true);
        }

        /* Author: Lawrence Chan
         * Description: As document need to get information before sending request to Solr.
         *              After get the information, check MS-Office document with embedded object
         *              If find embedded object in document, its will not send to solr to have indexing task,
         *              also insert a record to "Exclusion List Table".
         * */
        if ($this->configService->getAppValue('index_files_exclusion_list')) {
            if (!$this->checkMSDocEmbeddedObject($item)) {
                return false;
            }
        }
        
        $this->dataRetrievalFromPath($item);
        
        if ($item->isDeleted()) {
            $item->setShare();
            $item->setShareGroup();
        }
        
        return true;
    }

    /**
     * Author: Lawrence Chan
     * Description: The core logic for checking embedded object in MS-Office document
     * @param ItemDocument $item
     */
    public function checkMSDocEmbeddedObject(ItemDocument $item) {
        $this->miscService->log("--------------File ID-----------  " . $item->getId() . $item->getMimetype(), 0);
        if ($this->isMSMime($item->getMimetype())) {
            // Get absolute path of document
            $this->generateAbsolutePath($item);
            $this->miscService->log("Start extract the Office document |" . $item->getPath() . "|" . $item->getMimetype(), 0);

            if ($this->isMSMime($item->getMimetype(), true)) {
                // OLE
                $poi_path = __DIR__ . "/../../../jar/poi-3.16-beta2.jar";
                $cmd = 'java -cp /poi-3.16.jar org.apache.poi.poifs.dev.POIFSLister "' . $item->getAbsolutePath() . '"';
                $this->miscService->log($cmd, 0);
                exec($cmd, $result, $rc);

                // Check result
                for ( $i = 0; $i < sizeof($result); $i++) {
                    // "ObjectPool" for MS word only, Ole10Native both in MS-word and MS-excel

                    $isEmbedded = false;

                    switch ($item->getMimetype()) {
                        case 'application/vnd.ms-word':
                            $isEmbedded = strpos($result[$i], "ObjectPool") && !strpos($result[$i + 1], "no children");
                            break;
                        case 'application/msword':
                            $isEmbedded = strpos($result[$i], "ObjectPool") && !strpos($result[$i + 1], "no children");
                            break;
                        case 'application/vnd.ms-excel':
                            $isEmbedded = strpos($result[$i], "MBD");
                            break;
                        case 'application/msexcel':
                            $isEmbedded = strpos($result[$i], "MBD");
                            break;
//                        case 'application/vnd.ms-powerpoint':
//                            $isEmbedded = strpos($result[$i], "WordDocument") || strpos($result[$i], "Workbook");
//                            break;
//                        case 'application/mspowerpoint':
//                            $isEmbedded = strpos($result[$i], "WordDocument") || strpos($result[$i], "Workbook");
//                            break;
                        default:
                            $this->miscService->log("Not MS-Doc, MIME-Type: " . $item->getMimetype(), 0);
                    }
//                    $isEmbedded = strpos($result[$i], "ObjectPool") || strpos($result[$i], "Ole10Native");

                    $this->miscService->log("Checking text: " . $result[$i], 0);
                    if ($isEmbedded) {
                        $this->miscService->log("The file have embedded object", 0);
                        $this->miscService->log("Have ObjectPool", 0);$this->exclusionListMapper->existOrInsert(new ExclusionList($item->getId(), $item->getOwner(), $item->getPath()));
                        return false;
                    }
                }

                $this->miscService->log("RC: $rc", 0);
                $this->miscService->log("Return code dose not zero or have not embedded document");
            } else {
                // OOXML or Non-OLE
                $cmd = 'unzip -Zl "' . $item->getAbsolutePath() . '" | grep "embeddings" | wc -l';
                // Execute linux cmd
                exec($cmd, $result, $rc);
                // Debug result
                $this->miscService->log("----------------Result String: " . implode(" ",$result) . "--------------", 0);
                if ($rc == "0" && $result[0] != "0") {
                    $this->miscService->log("The file have embedded object", 0);
                    $this->exclusionListMapper->existOrInsert(new ExclusionList($item->getId(), $item->getOwner(), $item->getPath()));
                    return false;
                } else {
                    $this->miscService->log("RC: $rc", 0);
                    $this->miscService->log("Return code dose not zero or have not embedded document");
                }
            }
        } else {
            $this->miscService->log("Non-office file", 0);
        }
        return true;
    }

    /**
     * Author: Lawrence Chan
     * Description: Check document MimeType belongs to MS-Office
     * @param $mime
     * @param bool $checkOLE [default=false] if true, only for OLE2 checking
     * @return bool
     */
    public function isMSMime($mime, $checkOLE = false) {
        $filters = $this->configService->getFileFilters();
        $acceptedMimeType = array(
            'vnd' => array(
                'application/vnd.oasis.opendocument',
                'application/vnd.sun.xml',
                'application/vnd.openxmlformats-officedocument',
                'application/vnd.ms-word',
                'application/vnd.ms-powerpoint',
                'application/vnd.ms-excel',
                'application/msword',
                'application/mspowerpoint',
                'application/msexcel'
            )
        );

        if ($checkOLE) {
            $oleMimeType = array(
                'application/vnd.ms-word',
                'application/vnd.ms-powerpoint',
                'application/vnd.ms-excel',
                'application/msword',
                'application/mspowerpoint',
                'application/msexcel'
            );
            foreach ($oleMimeType as $ole) {
                if (substr($mime, 0, strlen($ole)) == $ole) {
                    if ($filters['office'] !== '1')
                        return false;
                    return true;
                }
            }
        } else {
            foreach ($acceptedMimeType['vnd'] as $mt) {
                if (substr($mime, 0, strlen($mt)) == $mt) {
                    if ($filters['office'] !== '1')
                        return false;
                    return true;
                }
            }
        }

        // default
        return false;
    }

    /**
     * generate a local file and set its path in the item/AbsolutePath
     *
     * @param ItemDocument $item            
     */
    public function generateAbsolutePath(&$item, &$ierror = '')
    {
        if ($item->isStorageLocal()) {
            $item->setAbsolutePath($this->view->getLocalFile($item->getPath()));
            return true;
        }
        
        // not local, not external nor encrypted, we generate temp file
        if (! $item->isExternal() && ! $item->isEncrypted()) {
            $item->setAbsolutePath($this->view->toTmpFile($item->getPath()), true);
            return true;
        }

		// not local, not external nor encrypted, we generate temp file
		if (! $item->isExternal() && ! $item->isEncrypted()) {
			try {
				$item->setAbsolutePath($this->view->toTmpFile($item->getPath()), true);
			} catch (StorageNotAvailableException $ex) {
				$ierror = new ItemError(ItemError::EXCEPTION_INDEXDOCUMENT_WITHOUT_ABSOLUTEPATH, $ex->getHint());
				return false;
			}
			return true;
		}


		// We generate a local tmp file from the remote one
        if ($item->isExternal() && $this->configService->getAppValue('index_files_external') === '1') {
            try {
                $item->setAbsolutePath($this->view->toTmpFile($item->getPath()), true);
            } catch (\OC\Encryption\Exceptions\DecryptionFailedException $dfe) {
                $ierror = new ItemError(ItemError::EXCEPTION_DECRYPTION_FAILED, $dfe->getHint());
                return false;
            } catch (\OC\Encryption\Exceptions\ModuleDoesNotExistsException $mod) {
                $ierror = new ItemError(ItemError::EXCEPTION_ENCRYPT_NO_MODULE, $mod->getHint());
                return false;
            }
            
            return true;
        }
        
        // We generate a local tmp file from the federated
        if ($item->isFederated() && $this->configService->getAppValue('index_files_federated') === '1') {
            $item->setAbsolutePath($this->view->toTmpFile($item->getPath()), true);
            return true;
        }
        
        // encrypted file = local tmp file
        if ($item->isEncrypted() && $this->configService->getAppValue('index_files_encrypted') === '1') {
            try {
                $item->setAbsolutePath($this->view->toTmpFile($item->getPath()), true);
            } catch (\OC\Encryption\Exceptions\DecryptionFailedException $dfe) {
                $ierror = new ItemError(ItemError::EXCEPTION_DECRYPTION_FAILED, $dfe->getHint());
                $ierror->link(ItemError::LINK_EXCEPTION_DECRYPTION_FAILED);
                return false;
            } catch (\OCA\Encryption\Exceptions\PrivateKeyMissingException $pkme) {
                $ierror = new ItemError(ItemError::EXCEPTION_DECRYPT_PRIVATEKEY_MISSING, $pkme->getHint());
                $ierror->link(ItemError::LINK_EXCEPTION_DECRYPT_PRIVATEKEY_MISSING);
                return false;
            }
            
            return true;
        }
    }

    /**
     * destroy local temp file
     *
     * @param unknown $item            
     */
    public function destroyTempDocument(&$item)
    {
        if ($item->getAbsolutePath() != null && $item->isTemp())
            unlink($item->getAbsolutePath());
    }

    /**
     * convert FileInfo to ItemDocument
     *
     * @param FileInfo $file            
     * @return boolean|\OCA\Nextant\Items\ItemDocument
     */
    public function getDocumentFromFile($file)
    {
        if ($file == null)
            return false;
        
        $item = new ItemDocument(ItemDocument::TYPE_FILE, $file->getId());
        $item->setOwner($this->userId);
        $item->setMTime($file->getMTime());
        $item->setMimetype($file->getMimeType());
        $item->setPath(str_replace('//', '/', $file->getPath()));
        $item->setSize($file->getSize());
        $item->storageLocal((($file->getStorage()
            ->isLocal()) ? true : false));
        
        if ($file->isEncrypted())
            $item->encrypted(true);
        
        if ($file->isMounted())
            $item->external(true);
        else {
            
            // not clean - but only way I found to check if not mounted IS federated ?
            if (method_exists($file->getMountPoint(), 'moveMount') && method_exists($file->getMountPoint(), 'removeMount'))
                $item->federated(true);
        }
        
        return $item;
    }

    /**
     * get files from a specific user
     *
     * @param number $userId            
     * @return array
     */
    public function getFilesPerUserId($dir, $options)
    {
        if (! $this->configured())
            return false;
        
        if ($this->userId === '')
            return false;
        
        $data = array();
        
        // Filesystem::tearDown();
        
        $userFolder = FileService::getUserFolder($this->rootFolder, $this->userId, $dir);
        if (! $userFolder || $userFolder == null)
            return $data;
        
        $folder = $userFolder->get('/');
        $files = $folder->search('%');
        
        foreach ($files as $file) {
            if ($file->getType() == \OCP\Files\FileInfo::TYPE_FOLDER && $this->configService->getAppValue('index_files_tree') !== '1')
                continue;
            
            if ($file->isShared() && $file->getStorage()->isLocal() && ! in_array('forceshared', $options))
                continue;
            
            $item = $this->getDocumentFromFile($file);
            $item->deleted(in_array('deleted', $options));
            
            if ($item && $item != false && $item != null)
                $data[$item->getType() . '_' . $item->getId()] = $item;
        }
        
        return $data;
    }

    /**
     * get files from a userid+fileid
     *
     * @param number $userId            
     * @param number $fileId            
     * @param array $options            
     * @return array
     */
    public function getFilesPerFileId($fileId, $options)
    {
        if (! $this->configured())
            return false;
        
        if ($this->userId === '')
            return false;
        
        if ($fileId == '')
            return false;
        
        $view = Filesystem::getView();
        
        $data = array();
        $file = self::getFileInfoFromFileId($fileId, $view, $this->miscService);
        
        if ($file == null && $this->configService->getAppValue('index_files_trash') === '1') {
            $trashview = new View('/' . $this->userId . '/files_trashbin/files');
            $file = self::getFileInfoFromFileId($fileId, $trashview, $this->miscService);
            array_push($options, 'deleted');
        }
        
        if ($file == null)
            return false;
        
        if ($file->getType() == \OCP\Files\FileInfo::TYPE_FOLDER) {
            $result = $this->getFilesPerPath($file->getPath(), $options);
            if (is_array($result) && sizeof($result) > 0)
                $data = array_merge($data, $result);
            
            return $data;
        }
        
        if ($file->isShared() && ! in_array('forceshared', $options))
            return $data;
        
        $item = $this->getDocumentFromFile($file);
        $item->deleted(in_array('deleted', $options));
        
        $data[$item->getType() . '_' . $item->getId()] = $item;
        
        return $data;
    }

    /**
     * get files/subdir from a userid+fileid
     *
     * @param number $userId            
     * @param number $fileId            
     * @param array $options            
     * @return array
     */
    private function getFilesPerPath($path, $options)
    {
        if (! $this->configured())
            return false;
        
        if ($this->userId === '')
            return false;
            
            // Filesystem::tearDown();
        $view = Filesystem::getView();
        
        $data = array();
        $file = $view->getFileInfo($path);
        if ($file == false | $file == null)
            return false;
        
        if ($file->getType() == \OCP\Files\FileInfo::TYPE_FOLDER) {
            
            $subfiles = $view->getDirectoryContent($file->getPath());
            foreach ($subfiles as $subfile) {
                $result = $this->getFilesPerPath($subfile->getPath(), $options);
                if (is_array($result) && sizeof($result) > 0)
                    $data = array_merge($data, $result);
            }
            return $data;
        }
        
        if ($file->isShared() && ! in_array('forceshared', $options))
            return $data;
        
        $item = $this->getDocumentFromFile($file);
        $item->deleted(in_array('deleted', $options));
        
        $data[$item->getType() . '_' . $item->getId()] = $item;
        
        return $data;
    }

    /**
     * update ItemDocument based on its filepath (sharing rights, noindex status, ..)
     *
     * @param ItemDocument $entry            
     * @return boolean
     */
    private function dataRetrievalFromPath(&$entry)
    {
        $data = array();
        
        $subpath = '';
        $subdirs = explode('/', $entry->getPath());
        foreach ($subdirs as $subdir) {
            
            if ($subdir == '')
                continue;
            
            $subpath .= '/' . $subdir;
            if (strlen($subpath) > 0 && $subpath != '/') {
                
                self::getShareRightsFromExternalMountPoint($this->externalMountPoint, $subpath, $data, $entry);
                // self::getIndexStatusFromExternalMountPoint($this->externalMountPoint, $subpath, $data, $entry);
                
                $subdirInfos = self::getFileInfoFromPath($subpath);
                if (! $subdirInfos)
                    continue;
                
                self::getShareRightsFromFileId($subdirInfos->getId(), $data);
                self::getIndexStatusFromFileInfo($this->view, $subdirInfos, $data);
            }
        }
        
        if (key_exists('noindex', $data))
            $entry->noIndex($data['noindex']);
        if (key_exists('share_users', $data))
            $entry->setShare($data['share_users']);
        if (key_exists('share_groups', $data))
            $entry->setShareGroup($data['share_groups']);
        
        return true;
    }

    private static function getShareRightsFromExternalMountPoint($mountPoints, $path, &$data, &$entry)
    {
        if (! $entry->isExternal())
            return false;
        
        if (! key_exists('share_users', $data))
            $data['share_users'] = array();
        if (! key_exists('share_groups', $data))
            $data['share_groups'] = array();
        
        $edited = false;
        foreach ($mountPoints as $mount) {
            if ($mount['path'] !== $path)
                continue;
            
            $edited = true;
            if (! $mount['personal']) {
                $entry->setOwner('__global');
                if (sizeof($mount['shares']['users']) == 1 && sizeof($mount['shares']['groups']) == 0 && $mount['shares']['users'][0] == 'all' && (! in_array('__all', $data['share_groups']))) {
                    array_push($data['share_groups'], '__all');
                    continue;
                }
            }
            
            foreach ($mount['shares']['users'] as $share_user) {
                if ($share_user != $entry->getOwner() && ! in_array($share_user, $data['share_users']))
                    array_push($data['share_users'], $share_user);
            }
            
            foreach ($mount['shares']['groups'] as $share_group) {
                if (! in_array($share_group, $data['share_groups']))
                    array_push($data['share_groups'], $share_group);
            }
        }
        
        return $edited;
    }

    /**
     * update ItemDocument share rights from a specific fileid / subfolder
     *
     * @param number $fileId            
     * @param ItemDocument $data            
     * @return boolean
     */
    private static function getShareRightsFromFileId($fileId, &$data)
    {
        if (! key_exists('share_users', $data))
            $data['share_users'] = array();
        if (! key_exists('share_groups', $data))
            $data['share_groups'] = array();
        if (! key_exists('deleted', $data))
            $data['deleted'] = false;
        
        $OCShares = Share::getAllSharesForFileId($fileId);
        foreach ($OCShares as $share) {
            if ($share['share_type'] == \OC\Share\Constants::SHARE_TYPE_USER && ! in_array($share['share_with'], $data['share_users']))
                array_push($data['share_users'], $share['share_with']);
            if ($share['share_type'] == \OC\Share\Constants::SHARE_TYPE_GROUP && ! in_array($share['share_with'], $data['share_groups']))
                array_push($data['share_groups'], $share['share_with']);
            if ($share['share_type'] == \OC\Share\Constants::SHARE_TYPE_LINK && ! in_array('__link_' . $share['id'], $data['share_users']))
                array_push($data['share_users'], '__link_' . $share['id']);
        }
        
        return true;
    }

    /**
     * update ItemDocument index status based on path
     *
     * @param number $fileId            
     * @param ItemDocument $data            
     * @return boolean
     */
    private static function getIndexStatusFromFileInfo($view, $fileInfo, &$data)
    {
        if (! key_exists('noindex', $data))
            $data['noindex'] = false;
        
        if ($data['noindex'] === true)
            return true;
        
        if ($fileInfo->getType() != \OCP\Files\FileInfo::TYPE_FOLDER)
            return false;
        
        $files = $view->getDirectoryContent($fileInfo->getPath());
        
        foreach ($files as $file) {
            if ($file->getName() === self::NOINDEX_FILE) {
                $data['noindex'] = true;
                return true;
            }
        }
        
        return false;
    }

    /**
     * complete data from a search result with more details about the file itself
     *
     * @param array $data            
     * @param string $base            
     * @param boolean $trashbin            
     * @return array[]
     */
    public function getSearchResult(&$item, $base = '', $trashbin = true)
    {
        if ($this->view === null || $this->userId === '')
            return false;
        
        $path = '';
        $fileData = null;
        try {
            $path = $this->view->getPath($item->getId());
            $fileData = $this->view->getFileInfo($path);
        } catch (NotFoundException $e) {
            $fileData = null;
        }
        
        if ($this->configService->getAppValue('index_files_trash') === '1' && $fileData == null && $trashbin) {
            try {
                $trashview = new View('/' . $this->userId . '/files_trashbin/files');
                $path = $trashview->getPath($item->getId());
                $fileData = $trashview->getFileInfo($path);
                $item->deleted(true);
            } catch (NotFoundException $e) {
                return false;
            }
        }
        
        if ($fileData == null || $fileData === false)
            return false;
        
        $pathParts = pathinfo($path);
        $basepath = str_replace('//', '/', '/' . $pathParts['dirname'] . '/');
        
        if (substr($path, - 1) == '/')
            $path = substr($path, 0, - 1);
        
        $dirpath = $pathParts['dirname'];
        
        if ($base !== '') {
            $path = substr($path, strpos($path, $base) + strlen($base));
            $dirpath = substr($dirpath, strpos($dirpath, $base) + strlen($base));
        }
        
        if ($dirpath === '')
            $dirpath = '/';
            
            // fileinfo entry
        $entry = \OCA\Files\Helper::formatFileInfo($fileData);
        $entry['dirpath'] = $dirpath;
        $entry['filename'] = $pathParts['basename'];
        $entry['name'] = ((substr($path, 0, 1) === '/') ? substr($path, 1) : $path);
        
        if ($item->isSharedPublic())
            $entry['permissions'] = \OCP\Constants::PERMISSION_READ;
        
        $item->setEntry($entry);
        
        $item->setPath($path);
        
        $item->valid(true);
        
        return true;
    }

    /**
     * returns fileId from a path
     *
     * @param string $path            
     * @param View $view            
     * @return boolean|number
     */
    public static function getFileInfoFromPath($path, $view = null)
    {
        if ($view == null)
            $view = Filesystem::getView();
        if ($view == null)
            return null;
        
        try {
            return $view->getFileInfo($path);
        } catch (NotFoundException $e) {
            return false;
        }
    }

    public static function getFileInfoFromFileId($fileId, $view = null, $misc)
    {
        try {
            if ($view == null)
                $view = Filesystem::getView();
            if ($view == null)
                return null;
            
            $path = $view->getPath($fileId);
            if ($path == null)
                return null;
            
            $file = $view->getFileInfo($path);
            if ($file == null)
                return null;
            
            return $file;
        } catch (NotFoundException $e) {
            return null;
        }
    }
}
