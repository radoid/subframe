<?php
/**
 * Implements the data model functionality
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Model
{
	const Null = NAN;

	// Data manipulation

	function __construct($data = null, $force = false) {
		if (is_array ($data)) {
			foreach (($force ? $data : $this) as $key => $value)
				if (isset ($data[$key]))
					$this->$key = $data[$key];
		} elseif (is_object ($data))
			foreach (($force ? $data : $this) as $key => $value)
				if (isset ($data->$key))
					$this->$key = $data->$key;
	}

	static function __set_state($data) {
		return new static($data, true);
	}

	function merge($data, $force = false) {
		foreach ($data as $key => $value)
			if (isset($value) || $force)
				$this->$key = $value;
		return $this;
	}

	public function trim() {
		foreach ($this as $key => $value)
			if (!isset($this->$key))
				unset($this->$key);
		return $this;
	}

	static function column(array $objects, $column, $key = '') {
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = $object->$column;
		return $array;
	}

	static function columns($objects, array $columns, $key = '') {
		if (is_object($objects))
			return (object)array_intersect_key((array)$objects, array_flip($columns));
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = array_intersect_key((array)$object, array_flip($columns));
		return $array;
	}

	function __toString () {
		$sql = "";
		foreach ($this as $column => $value)
			if (isset($this->$column))
				$sql = ($sql ? "$sql, " : "") . "$column = ".(is_float($value) && is_nan($value) ? 'NULL' : self::quote($value));
		return $sql;
	}

	private function keys() {
		$keys = "";
		foreach ($this as $key => $value)
			if (isset($this->$key))
				$keys .= ($keys ? ", " : "") . $key;
		return $keys;
	}

	private function values() {
		$values = "";
		foreach ($this as $key => $value)
			if (isset($this->$key))
				$values .= ($values ? ", " : "") . (is_float($value) && is_nan($value) ? "NULL" : self::quote($value));
		return $values;
	}


	// Database operations

	const TABLE = '', KEY = 'id', ORDER = '';

	/**
	 * @var \PDO
	 */
	static $pdo;

	function insert() {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		self::$pdo->exec($sql);
		return self::$pdo->lastInsertId();
	}

	function upsert() {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")
				ON DUPLICATE KEY UPDATE $this";
		self::$pdo->exec($sql);
	}

	function replace() {
		$sql = "REPLACE INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		self::$pdo->exec($sql);
	}

	function update($id = null) {
		$id = (isset($id) ? $id : $this->{static::KEY});
		if (is_array($id) && !$id)
			return;
		$sql = "UPDATE ".static::TABLE." SET $this WHERE ".static::KEY." IN (".self::quote($id).")";
		self::$pdo->exec($sql);
	}

	static function set($id, $data) {
		if ($id || !is_array($id))
			(new static($data))->update($id);
	}

	static function delete($id) {
		if (is_array($id) && !$id)
			return;
		$sql = "DELETE FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		self::$pdo->exec($sql);
	}

	static function exists($id) {
		$sql = "SELECT 1 FROM ".static::TABLE." WHERE ".static::KEY." = ".self::quote($id)." LIMIT 1";
		return self::result($sql);
	}

	/**
	 * Fetches one record by its ID, or multiple records in case of an array of IDs
	 * @param int|int[] $id
	 * @return static|static[]|null
	 */
	static function get($id) {
		$sql = "SELECT * FROM ".static::TABLE." WHERE ".static::KEY." = ".self::quote($id)." LIMIT 1";
		return static::fetch($sql);
	}

	/**
	 * Fetches all records, optionally paged, optionally with keys from a column
	 * @param int $limit
	 * @param int $after_id
	 * @param string $key
	 * @return static[]|null
	 */
	static function getAll($limit = 0, $after_id = 0) {
		$sql = "SELECT * FROM ".static::TABLE
				.($after_id ? " WHERE ".static::KEY.($limit > 0 ? " > ":" < ").self::quote($after_id) : "")
				." ORDER BY ".static::KEY." DESC"
				.($limit ? " LIMIT ".intval($limit) : "");
		return static::fetchAll($sql);
	}

	/**
	 * Fetches a record by direct SQL query
	 * @param string $sql
	 * @return static|null
	 */
	static function fetch($q) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q);
		return $q->fetchObject(static::class != 'Model' ? static::class : 'stdClass');
	}

	/**
	 * Fetches records by direct SQL query, optionally with keys from a column
	 * @param string $sql
	 * @param string $key
	 * @return static[]
	 */
	static function fetchAll($q, $keyColumn = '') {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q);
		$classname = (static::class != 'Model' ? static::class : 'stdClass');
		if (!$keyColumn)
			return $q->fetchAll(PDO::FETCH_CLASS, $classname);
		for ($objects = []; ($o = $q->fetchObject($classname)); $objects[$o->$keyColumn] = $o);
		return $objects;
	}

	/**
	 * Fetches a single result
	 * @param $sql string
	 * @return string|false
	 */
	static function result($q) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q);
		return $q->fetchColumn();
	}

	static function allResults($q) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q);
		return $q->fetchAll(PDO::FETCH_COLUMN);
	}

	static function query(string $sql, array $params = null) {
		$stmt = self::$pdo->{$params ? 'prepare':'query'}($sql);
		if (!$stmt)
			throw new \Exception(self::$pdo->errorInfo()[2], 500);
		if ($params)
			$stmt->execute($params);
		return $stmt;
	}

	static function quote($str) {
		if (is_array($str))
			return implode(',', array_map([self::class, 'quote'], $str));
		if (!self::$pdo)
			return "'".addslashes($str)."'";
		return self::$pdo->quote($str);
	}

}
