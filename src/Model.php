<?php
namespace Subframe;

use PDO;
use PDOStatement;

/**
 * Implements the data model functionality
 *
 * @package Subframe PHP Framework
 */
class Model {

	/**
	 * Database table name
	 * @var string
	 */
	const TABLE = '';

	/**
	 * Key column in the database table
	 * @var string
	 */
	const KEY = 'id';

	/**
	 * Default column used for sorting
	 * @var string
	 */
	const ORDER = '';

	/** Represents NULL value */
	const Null = NAN;

	/**
	 * The database interface
	 * @var PDO
	 */
	static $pdo;


	// Data manipulation

	/**
	 * Model constructor, optionally initialising from a data array
	 * (only fields that are part of the model are taken)
	 * @param array|null $data associative array with initial data
	 */
	public function __construct($data = null) {
		foreach ($this as $key => $value)
			if (isset($data[$key]))
				$this->$key = $data[$key];
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
	 * The fields present in the record, as a string suitable for use in SQL queries
	 * @return string
	 */
	public function keys() {
		$keys = "";
		foreach ($this as $key => $value)
			if (isset($value))
				$keys .= ($keys ? ", " : "") . $key;
		return $keys;
	}

	/**
	 * The values present in the record, quoted, as a string suitable for use in SQL queries
	 * @return string
	 */
	public function values() {
		$values = "";
		foreach ($this as $key => $value)
			if (isset($value))
				$values .= ($values ? ", " : "") . (is_float($value) && is_nan($value) ? "NULL" : self::quote($value));
		return $values;
	}


	// Database operations

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
	 * Inserts the object into the DB table, fully replacing the existing record if there's a duplicate key
	 * @return int The number of rows affected
	 */
	public function replace() {
		$sql = "REPLACE INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Updates the record(s) in the DB table, found by the ID(s) from the optional parameter or the object itself
	 * @param string|string[]|null $id Optional value looked up in the key column
	 * @return int The number of rows affected
	 */
	public function update($id = null) {
		$id = (isset($id) ? $id : $this->{static::KEY});
		$sql = strval($this);
		if ((is_array($id) && !$id) || !$sql)
			return 0;
		$sql = "UPDATE ".static::TABLE." SET $sql WHERE ".static::KEY." IN (".self::quote($id).")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Deletes DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 * @return int The number of rows affected
	 */
	public static function delete($id) {
		if (is_array($id) && !$id)
			return 0;
		$sql = "DELETE FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Fetches one or more records by their ID(s)
	 * @param string|string[] $id The ID(s) to look for
	 * @return static|static[]|null The record(s) if found, or null
	 */
	public static function get($id) {
		$sql = "SELECT * FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		if (!is_array($id))
			return static::fetch("$sql LIMIT 1");
		elseif ($id)
			return static::fetchAll($sql);
		return [];
	}

	/**
	 * Fetches all records, optionally paged, optionally with keys from a column
	 * @param int $limit Optionally, maximum number of records
	 * @param string|null $after_id Optionally, the last ID from the previous request
	 * @param int $page
	 * @return static[]|null
	 */
	public static function getAll($limit = 0, $after_id = 0, $page = 0) {
		$sql = "SELECT * FROM ".static::TABLE
				.($after_id ? " WHERE ".static::KEY.($limit > 0 ? " > ":" < ").self::quote($after_id) : "")
				." ORDER BY ".static::KEY.($limit >= 0 ? " ASC":" DESC")
				.($limit ? " LIMIT ".($page ? abs($page*$limit).',':'') . abs($limit) : "");
		return static::fetchAll($sql);
	}

	/**
	 * Fetches a record by direct SQL query
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return static|null The record or null
	 */
	public static function fetch($q, $params = null) {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		return $q->fetchObject(static::class != 'Subframe\Model' ? static::class : 'stdClass');
	}

	/**
	 * Fetches records by direct SQL query, optionally with keys from a column
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @param string $keyColumn Optional column to serve as the resulting array key
	 * @return static[] The records
	 */
	public static function fetchAll($q, $params = null, $keyColumn = '') {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		$class = (static::class != 'Subframe\Model' ? static::class : 'stdClass');
		if (!$keyColumn)
			return $q->fetchAll(PDO::FETCH_CLASS, $class);
		for ($objects = []; ($o = $q->fetchObject($class)); $objects[$o->$keyColumn] = $o) {}
		return $objects;
	}

	/**
	 * Fetches a single result
	 * @param PDOStatement|string $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string|null
	 */
	public static function result($q, $params = null) {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		$result = $q->fetchColumn();
		return ($result !== false ? $result : null);
	}

	/**
	 * Fetches a column by direct SQL query
	 * @param string|PDOStatement $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string[]
	 */
	public static function allResults($q, $params = null) {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		return $q->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * Performs an SQL query, optionally a prepared statement
	 * @param string $sql The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return PDOStatement
	 */
	public static function query($sql, $params = null) {
		if ($params) {
			$stmt = self::$pdo->prepare($sql);
			$stmt->execute($params);
		} else
			$stmt = self::$pdo->query($sql);
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
	 * @param string $dsn The DNS describing the database
	 * @param string $username [optional]
	 * @param string $password [optional]
	 * @param array $options [optional] Additional PDO options
	 */
	public static function connect($dsn, $username = '', $password = '', $options = []) {
		self::$pdo = new PDO($dsn, $username, $password, $options + [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]);
	}

}
