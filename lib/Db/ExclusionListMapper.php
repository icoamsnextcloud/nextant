<?php
/**
 * db       .d8b.  db   d8b   db d8888b. d88888b d8b   db  .o88b. d88888b
 * 88      d8' `8b 88   I8I   88 88  `8D 88'     888o  88 d8P  Y8 88'
 * 88      88ooo88 88   I8I   88 88oobY' 88ooooo 88V8o 88 8P      88ooooo
 * 88      88~~~88 Y8   I8I   88 88`8b   88~~~~~ 88 V8o88 8b      88~~~~~
 * 88booo. 88   88 `8b d8'8b d8' 88 `88. 88.     88  V888 Y8b  d8 88.
 * Y88888P YP   YP  `8b8' `8d8'  88   YD Y88888P VP   V8P  `Y88P' Y88888P
 *
 * Time: 3/4/2017 15:31
 * File Name: ExclusionListMapper.php
 * Description: Database mapper class for exclusion list
 */

namespace OCA\Nextant\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\IDBConnection;
use OCP\AppFramework\Db\Mapper;

class ExclusionListMapper extends Mapper
{
    const TABLENAME = 'nextant_exclusion_list';

    public function __construct(IDBConnection $db)
    {
        parent::__construct($db, self::TABLENAME, 'OCA\Nextant\Db\ExclusionList');
    }

    public function find($id)
    {
        $sql = 'SELECT * FROM *PREFIX*' . self::TABLENAME . ' WHERE id = ?';
        return $this->findEntity($sql, [
            $id
        ]);
    }

    public function findByUser($owner)
    {
        $sql = 'SELECT * FROM *PREFIX*' . self::TABLENAME . ' WHERE owner = ?';
        return $this->findEntities($sql, [
            $owner
        ]);
    }

    public function existOrInsert($entity)
    {
        try {
            $sql = 'SELECT * FROM *PREFIX*' . self::TABLENAME . ' WHERE fileid = ?';
            $this->findEntity($sql, [
                $entity->getFileid()
            ]);
        } catch (DoesNotExistException $dnee) {
            $this->insert($entity);
        }
    }

    public function next($keepit = false)
    {
        try {
            // $sql = 'SELECT * FROM *PREFIX*' . self::TABLENAME . ' ORDER BY id ASC LIMIT 0, 1';
            $sql = 'SELECT * FROM *PREFIX*' . self::TABLENAME . ' ORDER BY id ASC LIMIT 1';
            $result = $this->findEntity($sql, []);
        } catch (\OCP\AppFramework\Db\DoesNotExistException $dnee) {
            return false;
        }

        if (! $keepit) {
            $this->delete($result);
        }

        return $result;
    }

    public function clear()
    {
        $sql = 'TRUNCATE *PREFIX*' . self::TABLENAME;
        return $this->execute($sql);
    }
}