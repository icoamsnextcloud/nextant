<?php
/**
 * db       .d8b.  db   d8b   db d8888b. d88888b d8b   db  .o88b. d88888b
 * 88      d8' `8b 88   I8I   88 88  `8D 88'     888o  88 d8P  Y8 88'
 * 88      88ooo88 88   I8I   88 88oobY' 88ooooo 88V8o 88 8P      88ooooo
 * 88      88~~~88 Y8   I8I   88 88`8b   88~~~~~ 88 V8o88 8b      88~~~~~
 * 88booo. 88   88 `8b d8'8b d8' 88 `88. 88.     88  V888 Y8b  d8 88.
 * Y88888P YP   YP  `8b8' `8d8'  88   YD Y88888P VP   V8P  `Y88P' Y88888P
 *
 * Time: 3/4/2017 15:27
 * File Name: ExclusionList.php
 * Description: Database entity class for exclusion list
 */

namespace OCA\Nextant\Db;

use OCP\AppFramework\Db\Entity;

class ExclusionList extends Entity
{
    public $id;

    public $fileid;

    public $owner;

    public $path;

    public function __construct($fileid = null, $owner = null, $path = null)
    {
        $this->setFileid($fileid);
        $this->setOwner($owner);
        $this->setPath($path);
    }
}