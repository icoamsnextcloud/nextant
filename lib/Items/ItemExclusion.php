<?php
/**
 * db       .d8b.  db   d8b   db d8888b. d88888b d8b   db  .o88b. d88888b
 * 88      d8' `8b 88   I8I   88 88  `8D 88'     888o  88 d8P  Y8 88'
 * 88      88ooo88 88   I8I   88 88oobY' 88ooooo 88V8o 88 8P      88ooooo
 * 88      88~~~88 Y8   I8I   88 88`8b   88~~~~~ 88 V8o88 8b      88~~~~~
 * 88booo. 88   88 `8b d8'8b d8' 88 `88. 88.     88  V888 Y8b  d8 88.
 * Y88888P YP   YP  `8b8' `8d8'  88   YD Y88888P VP   V8P  `Y88P' Y88888P
 *
 * Time: 3/4/2017 16:03
 * File Name: ItemExclusion.php
 * Description: Item object class for exclusion
 */

namespace OCA\Nextant\Items;


class ItemExclusion
{
    private $userId;

    private $fileId;

    private $path;

    private $date;

    public function __construct($item = array())
    {
        if (is_array($item)) {
            if (key_exists('userid', $item))
                $this->setUserId($item['userid']);
            if (key_exists('fileid', $item))
                $this->setFileId($item['fileid']);
            if (key_exists('path', $item))
                $this->setPath($item['path']);
        }

        if (is_object($item)) {
            if (isset($item->userid))
                $this->setUserId($item->userid);
            if (isset($item->fileid))
                $this->setFileId($item->fileid);
            if (isset($item->path))
                $this->setPath($item->path);
        }
    }

    /**
     * @return mixed
     */
    public function getUserId()
    {
        return $this->userId;
    }

    /**
     * @param mixed $userId
     */
    public function setUserId($userId)
    {
        $this->userId = $userId;
    }

    /**
     * @return mixed
     */
    public function getFileId()
    {
        return $this->fileId;
    }

    /**
     * @param mixed $fileId
     */
    public function setFileId($fileId)
    {
        $this->fileId = $fileId;
    }

    /**
     * @return mixed
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param mixed $path
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date)
    {
        $this->date = $date;
    }
}