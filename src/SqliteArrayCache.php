<?php

namespace IDCT;

/**
 * @experimental
 * @deprecated
 */
class SqliteArrayCache implements \ArrayAccess
{
    /**
     * Path to which save and from which to load files
     * @var string
     */
    protected $cachePath;
    protected $db;

    /**
     * Constructs the new object. Requires a cache path to be given.
     *
     * @param string $cachePath With the trailing slash
     * @throws \Exception Could not initialize the cache directory.
     */
    public function __construct($cachePath, $reset, $memory = false)
    {
        $this->cachePath = $cachePath;
        if (!file_exists($cachePath)) {
            if (false === mkdir($cachePath, 0777, true)) {
                throw new \Exception("Could not initialize the cache directory.");
            }
        }

        $cacheFile = $cachePath . 'cache.db';
        if (file_exists($cacheFile) && $reset === true) {
            unlink($cacheFile);
        }

        $this->db = new \SQLite3($cacheFile);
        $this->db->exec("PRAGMA synchronous = OFF");
//        $this->db->exec("PRAGMA journal_mode = MEMORY");
        $this->db->exec("PRAGMA page_size = 31457280");
//        $this->db->exec("PRAGMA temp_store = MEMORY");
        $this->db->exec("PRAGMA count_changes = OFF");
        $this->db->exec('BEGIN;
CREATE TABLE cache (
    key VARCHAR(48) PRIMARY KEY,
    value BLOB
    );
CREATE INDEX key_idx ON cache (key);
COMMIT;');
    }

    /**
     * Gets the cache path
     * @return string
     */
    public function getCachePath()
    {
        return $this->cachePath;
    }

    public function startImport()
    {
        $this->db->exec("BEGIN TRANSACTION;");

        return $this;
    }

    public function endImport()
    {
        $this->db->exec("END TRANSACTION;");

        return $this;
    }

    /**
     * Saves the serialized value to the the $cachePath with the given name
     * @param string $offset ID of the cache key (filename)
     * @param string $value Value to be serialized and saved
     */
    public function offsetSet($offset, $value)
    {
        /*
        $filePath = $this->getCachePath() . $offset;
        if($this->offsetExists($filePath)) {
            unlink($filePath);
        }
        file_put_contents($filePath, serialize($value));
        */
        $stmt = $this->db->prepare('INSERT OR REPLACE INTO cache (key, value) VALUES (:key, :value)');
        $stmt->bindValue(':key', $offset);
        $stmt->bindValue(':value', serialize($value), SQLITE3_BLOB);
        $stmt->execute();
    }

    /**
     * Checks if cache key (filename) exists
     * @param string $offset Cache key (Filename)
     * @return boolean
     */
    public function offsetExists($offset)
    {
        $value = $this->db->querySingle('SELECT value FROM cache WHERE key = "'.$offset.'"');
        if ($value === false || $value === null) {
            return false;
        }

        return true;
    }

    /**
     * Removes cache key and value (file)
     * @param string $offset Cache key (Filename)
     */
    public function offsetUnset($offset)
    {
        $this->db->exec('DELETE FROM cache WHERE key = "'.$offset.'"');
    }

    /**
     * Gets the value from under the given cache key (filename)
     * @param string $offset Cache key (filename)
     * @return mixed
     */
    public function offsetGet($offset)
    {
        $value = $this->db->querySingle('SELECT value FROM cache WHERE key = "'.$offset.'"');
        if ($value === false || $value === null) {
            return null;
        }

        return unserialize($value);
    }

    public function pop($offset)
    {
        $value = $this->db->querySingle('SELECT value FROM cache WHERE key = "'.$offset.'"');
        $this->db->exec('DELETE FROM cache WHERE key = "'.$offset.'"');
        if ($value === false || $value === null) {
            return null;
        }

        return unserialize($value);
    }
}
