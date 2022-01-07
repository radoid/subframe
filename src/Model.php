<?php
namespace Subframe;

/**
 * Implements the MVC model functionality
 * @package Subframe PHP Framework
 */
class Model {

	// Data manipulation

	/**
	 * Model constructor, optionally initialising from a data array
	 * (only fields that are part of the model are taken)
	 * @param array|object|null $data object or associative array with initial data
	 */
	public function __construct($data = null) {
		if (is_array ($data)) {
			foreach ($this as $key => $value)
				if (isset($data[$key]))
					$this->$key = $data[$key];
		} elseif (is_object ($data))
			foreach ($this as $key => $value)
				if (isset($data->$key))
					$this->$key = $data->$key;
	}

	/**
	 * Instantiates an object of the static class with data from an array
	 * @param $data
	 * @return static
	 */
	public static function __set_state($data) {
		return new static($data);
	}

	/**
	 * Adds all fields from the given object/array to the actual object
	 * @param array|object $data object or associative array with data
	 * @param bool $force should null values be copied over
	 * @return $this
	 */
	public function merge($data, $force = false) {
		foreach ($data as $key => $value)
			if (isset($value) || $force)
				$this->$key = $value;
		return $this;
	}

	/**
	 * Removes null fields from the object
	 * @return $this
	 */
	public function trim() {
		foreach ($this as $key => $value)
			if (!isset($this->$key))
				unset($this->$key);
		return $this;
	}

	/**
	 * Represents the object with a string, suitably for use in SQL queries
	 * @return string The SQL compatible string
	 */
	public function __toString () {
		$sql = "";
		foreach ($this as $column => $value)
			if (isset($this->$column))
				$sql = ($sql ? "$sql, " : "") . "$column = ".(is_float($value) && is_nan($value) ? 'NULL' : self::quote($value));
		return $sql;
	}

	/**
	 * The fields present in the object, as a string suitable for use in SQL queries
	 * @return string
	 */
	public function keys() {
		$keys = "";
		foreach ($this as $key => $value)
			if (isset($this->$key))
				$keys .= ($keys ? ", " : "") . $key;
		return $keys;
	}

	/**
	 * The values present in the object, quoted, as a string suitable for use in SQL queries
	 * @return string
	 */
	public function values() {
		$values = "";
		foreach ($this as $key => $value)
			if (isset($this->$key))
				$values .= ($values ? ", " : "") . (is_float($value) && is_nan($value) ? "NULL" : self::quote($value));
		return $values;
	}

	/**
	 * Extracts one field across multiple objects, optionally indexing the resulting array
	 * @param array $objects The objects containing the field
	 * @param string $field The field to be extracted
	 * @param string|null $index Optionally, the field to serve as index
	 * @return array
	 */
	public static function column(array $objects, $field, $index = null) {
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$index ? $object->$index : $i++] = $object->$field;
		return $array;
	}

	/**
	 * Extracts multiple fields across single or multiple objects, optionally indexing the result
	 * @param array|object $objects The objects containing the fields
	 * @param array $fields The fields to be extracted
	 * @param string|null $index Optionally, the field to serve as index
	 * @return array|object
	 */
	public static function columns($objects, array $fields, $index = null) {
		$fields = array_flip($fields);
		if (is_object($objects))
			return (object)array_intersect_key((array)$objects, $fields);
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$index ? $object->$index : $i++] = (object)array_intersect_key((array)$object, $fields);
		return $array;
	}


	// Database operations

	/**
	 * Database table name
	 * @var string
	 */
	public const TABLE = '';

	/**
	 * Key column in the database table
	 * @var string
	 */
	public const KEY = 'id';

	/**
	 * Default column used for sorting
	 * @var string
	 */
	public const ORDER = null;

	/**
	 * Represents NULL value in the database
	 */
	public const Null = NAN;

	/**
	 * The database interface
	 * @var \PDO
	 */
	public static $pdo;

	/**
	 * Inserts the object into the DB table
	 * @return string The insert ID
	 */
	public function insert() {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		self::$pdo->exec($sql);
		return self::$pdo->lastInsertId();
	}

	/**
	 * Inserts the object into the DB table, updating the existing record if there's a duplicate key
	 */
	public function upsert() {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")
				ON DUPLICATE KEY UPDATE $this";
		self::$pdo->exec($sql);
	}

	/**
	 * Inserts the object into the DB table, fully replacing the existing record if there's a duplicate key
	 */
	public function replace() {
		$sql = "REPLACE INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		self::$pdo->exec($sql);
	}

	/**
	 * Updates the record(s) in the DB table, found by the ID(s) from the optional parameter or the object itself
	 * @param string|string[] $id Optional value looked up in the key column
	 */
	public function update($id = '') {
		$id = ($id ?? $this->{static::KEY});
		$sql = strval($this);
		if (is_array($id) && !$id || !$sql)
			return;
		$sql = "UPDATE ".static::TABLE." SET $sql WHERE ".static::KEY." IN (".self::quote($id).")";
		self::$pdo->exec($sql);
	}

	/**
	 * Updates only selected columns, as given by the data array/object, in the DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 * @param array|object $data
	 */
	public static function set($id, $data) {
		if ($id || !is_array($id))
			(new static($data))->update($id);
	}

	/**
	 * Deletes DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 */
	public static function delete($id) {
		if (is_array($id) && !$id)
			return;
		$sql = "DELETE FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		self::$pdo->exec($sql);
	}

	/**
	 * Returns the total record count in the table
	 * @return int
	 * @throws \Exception
	 */
	public static function count() {
		$sql = "SELECT COUNT(*) FROM ".static::TABLE;
		$count = self::result($sql);
		return (int) $count;
	}

	/**
	 * Tells whether any records exist having given ID(s)
	 * @param string|string[] $id ID(s) to look for
	 * @return boolean
	 * @throws \Exception
	 */
	public static function exists($id) {
		$sql = "SELECT 1 FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).") LIMIT 1";
		return !!self::result($sql);
	}

	/**
	 * Fetches one or more records by their ID(s)
	 * @param string|string[] $id The ID(s) to look for
	 * @return static|static[]|null The record
	 * @throws \Exception
	 */
	public static function get($id) {
		$sql = "SELECT * FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		if (!is_array($id))
			return static::fetch($sql);
		elseif ($id)
			return static::fetchAll($sql);
		return [];
	}

	/**
	 * Fetches all records, optionally paged
	 * @param int $limit Optionally, maximum number of records
	 * @param string $after_id Optionally, the last ID from the previous request
	 * @param int $page Optional page number, starting from zero
	 * @return static[]|null
	 * @throws \Exception
	 */
	public static function getAll($limit = 0, $after_id = '', $page = 0) {
		$sql = "SELECT * FROM ".static::TABLE
				.($after_id ? " WHERE ".static::KEY.($limit > 0 ? " > ":" < ").self::quote($after_id) : "")
				." ORDER BY ".(static::ORDER ?? static::KEY).($limit >= 0 ? " ASC":" DESC")
				.($limit ? " LIMIT ".($page ? abs($page*$limit).',':'') . abs($limit) : "");
		return static::fetchAll($sql);
	}

	/**
	 * Fetches a record by direct SQL query
	 * @param string|\PDOStatement $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return static|null The record
	 * @throws \Exception
	 */
	public static function fetch($q, array $params = null) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q, $params);
		return $q->fetchObject(static::class != 'Subframe/Model' ? static::class : 'stdClass') ?: null;
	}

	/**
	 * Fetches records by direct SQL query, optionally indexed by a column
	 * @param string|\PDOStatement $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @param string $indexColumn
	 * @return static[] The records
	 * @throws \Exception
	 */
	public static function fetchAll($q, array $params = null, $indexColumn = '') {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q, $params);
		$classname = (static::class != 'Subframe\Model' ? static::class : 'stdClass');
		if (!$indexColumn)
			return $q->fetchAll(\PDO::FETCH_CLASS, $classname);
		for ($objects = []; ($o = $q->fetchObject($classname)); $objects[$o->$indexColumn] = $o);
		return $objects;
	}

	/**
	 * Fetches a single result (column) by direct SQL query
	 * @param string|\PDOStatement $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string|null
	 * @throws \Exception
	 */
	public static function result($q, array $params = null) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q, $params);
		$result = $q->fetchColumn();
		return $result !== false ? $result : null;
	}

	/**
	 * Fetches a column by direct SQL query
	 * @param string|\PDOStatement $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string[]
	 * @throws \Exception
	 */
	public static function allResults($q, array $params = null) {
		if (!$q instanceof \PDOStatement)
			$q = self::query($q, $params);
		return $q->fetchAll(\PDO::FETCH_COLUMN);
	}

	/**
	 * Performs an SQL query, optionally a prepared statement
	 * @param string $sql The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return \PDOStatement
	 * @throws \Exception
	 */
	public static function query(string $sql, array $params = null) {
		if ($params)
			$stmt = self::$pdo->prepare($sql);
		else
			$stmt = self::$pdo->query($sql);
		if (!$stmt)
			throw new \Exception(self::$pdo->errorInfo()[2], 500);
		if ($params)
			$stmt->execute($params);
		return $stmt;
	}

	/**
	 * Escapes and quotes a string
	 * @param string|string[] $str
	 * @return string
	 */
	public static function quote($str) {
		if (is_array($str))
			return implode(',', array_map([self::class, 'quote'], $str));
		if (!self::$pdo)
			return "'".addslashes($str)."'";
		return self::$pdo->quote($str);
	}

	/**
	 * Sets up the PDO object representing the database connection
	 * @param string $dsn
	 * @param string $username [optional]
	 * @param string $password [optional]
	 * @param array $options [optional]
	 */
	public static function connect(string $dsn, string $username = '', string $password = '', array $options = []) {
		self::$pdo = new \PDO($dsn, $username, $password, $options + [
				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC
		]);
	}

}
