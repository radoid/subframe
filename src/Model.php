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
	 * @var ?string
	 */
	public const TABLE = null;

	/**
	 * Key column in the database table
	 * @var ?string
	 */
	public const KEY = 'id';

	/**
	 * Default column used for sorting
	 * @var ?string
	 */
	public const ORDER = null;

	/** Represents NULL value */
	public const Null = NAN;

	/**
	 * The database interface
	 * @var PDO
	 */
	private static $pdo;


	// Data manipulation

	/**
	 * Model constructor, optionally initialising from a data array
	 * (only fields that are part of the model are taken)
	 * @param array|null $data associative array with initial data
	 */
	public function __construct(?array $data = null) {
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
	 * Adds all fields from the given object/array to the actual object
	 * @param array|object $data object or associative array with data
	 * @return $this
	 */
	public function merge($data) {
		foreach ($data as $key => $value)
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
	public function __toString(): string {
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
	public function keys(): string {
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
	public function values(): string {
		$values = "";
		foreach ($this as $key => $value)
			if (isset($value))
				$values .= ($values ? ", " : "") . (is_float($value) && is_nan($value) ? "NULL" : self::quote($value));
		return $values;
	}

	/**
	 * Extracts one field across multiple objects, optionally indexing the resulting array
	 * @param array $objects The objects containing the field
	 * @param string $field The field to be extracted
	 * @param string|null $key Optionally, the field to serve as the array key
	 * @return array
	 */
	public static function column(array $objects, string $field, ?string $key = null): array {
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = $object->$field;
		return $array;
	}

	/**
	 * Extracts multiple fields across single or multiple objects, optionally indexing the result
	 * @param array|object $objects The objects containing the fields
	 * @param array $fields The fields to be extracted
	 * @param string|null $key Optionally, the field to serve as the array key
	 * @return array|object
	 */
	public static function columns($objects, array $fields, ?string $key = null) {
		if (is_object($objects))
			return (object)array_intersect_key((array)$objects, array_flip($fields));
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = (object)array_intersect_key((array)$object, array_flip($fields));
		return $array;
	}


	// Database operations

	/**
	 * Inserts the object into the DB table
	 * @return string The insert ID
	 */
	public function insert(): string {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		self::$pdo->exec($sql);
		return self::$pdo->lastInsertId();
	}

	/**
	 * Inserts the object into the DB table, updating the existing record if there's a duplicate key
	 * @return int The number of rows affected
	 */
	public function upsert(): int {
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")
				ON DUPLICATE KEY UPDATE $this";
		return self::$pdo->exec($sql);
	}

	/**
	 * Inserts the object into the DB table, fully replacing the existing record if there's a duplicate key
	 * @return int The number of rows affected
	 */
	public function replace(): int {
		$sql = "REPLACE INTO ".static::TABLE."(".$this->keys().") VALUES (".$this->values().")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Updates the record(s) in the DB table, found by the ID(s) from the optional parameter or the object itself
	 * @param string|string[]|null $id Optional value looked up in the key column
	 * @return int The number of rows affected
	 */
	public function update($id = null): int {
		$id = ($id ?? $this->{static::KEY});
		$sql = strval($this);
		if ((is_array($id) && !$id) || !$sql)
			return 0;
		$sql = "UPDATE ".static::TABLE." SET $sql WHERE ".static::KEY." IN (".self::quote($id).")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Updates only selected columns, as given by the data array/object, in the DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 * @param array|object $data
	 * @return int The number of affected rows
	 */
	public static function set($id, $data): int {
		if (is_array($id) && !$id)
			return 0;
		return (new static($data))->update($id);
	}

	/**
	 * Deletes DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 * @return int The number of rows affected
	 */
	public static function delete($id): int {
		if (is_array($id) && !$id)
			return 0;
		$sql = "DELETE FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).")";
		return self::$pdo->exec($sql);
	}

	/**
	 * Tells whether any records exist having given ID(s)
	 * @param string|string[] $id ID(s) to look for
	 * @return boolean
	 */
	public static function exists($id): bool {
		$sql = "SELECT 1 FROM ".static::TABLE." WHERE ".static::KEY." IN (".self::quote($id).") LIMIT 1";
		return self::result($sql);
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
	 * @return static[]
	 */
	public static function getAll(int $limit = 0, ?string $after_id = null, int $page = 0): array {
		$sql = "SELECT * FROM ".static::TABLE
				.(isset($after_id) ? " WHERE ".static::KEY.($limit > 0 ? " > ":" < ").self::quote($after_id) : "")
				." ORDER BY ".(static::ORDER ?? static::KEY).($limit >= 0 ? " ASC":" DESC")
				.($limit ? " LIMIT ".($page ? abs($page*$limit).',':'') . abs($limit) : "");
		return static::fetchAll($sql);
	}

	/**
	 * Fetches a record by direct SQL query
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return static|null The record or null
	 */
	public static function fetch($q, ?array $params = null) {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		return $q->fetchObject(static::class != 'Subframe/Model' ? static::class : 'stdClass') ?: null;
	}

	/**
	 * Fetches records by direct SQL query, optionally indexed by a column
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @param string|null $keyColumn
	 * @return static[] The records
	 */
	public static function fetchAll($q, ?array $params = null, ?string $keyColumn = null): array {
		if (!$q instanceof PDOStatement)
			$q = self::query($q, $params);
		$class = (static::class != 'Subframe\\Model' ? static::class : 'stdClass');
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
	public static function result($q, ?array $params = null): ?string {
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
	public static function allResults($q, ?array $params = null): array {
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
	public static function query(string $sql, ?array $params = null): PDOStatement {
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
	public static function quote($str): string {
		if (is_array($str))
			return implode(',', array_map([self::class, 'quote'], $str));
		if (!self::$pdo)
			return "'".addslashes($str ?? '')."'";
		return self::$pdo->quote($str ?? '');
	}

	/**
	 * Sets up the PDO object representing the database connection
	 * @param string $dsn The DNS describing the database
	 * @param string|null $username [optional]
	 * @param string|null $password [optional]
	 * @param array $options [optional] Additional PDO options
	 */
	public static function connect(string $dsn, ?string $username = null, ?string $password = null, array $options = []): void {
		self::$pdo = new PDO($dsn, $username, $password, $options + [
				PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
				PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]);
	}

}
