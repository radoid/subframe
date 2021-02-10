<?php
namespace Subframe;

use Exception;
use Generator;
use PDO;
use PDOStatement;

/**
 * Implements the repository pattern for the model functionality
 * @package Subframe PHP Framework
 */
abstract class Repository {

	/**
	 * Entity class corresponding to this repository
	 * @var string
	 */
	public const ENTITY = 'stdClass';

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
	private $pdo;

	/**
	 * Current transaction level
	 * @var int
	 */
	private $transactionLevel = 0;


	/**
	 * @param PDO $pdo
	 */
	public function __construct(PDO $pdo) {
		$this->pdo = $pdo;
	}

	/**
	 * The fields present in a record, as a string suitable for use in SQL queries
	 * @param Entity $instance
	 * @return string
	 */
	public function keys(Entity $instance): string {
		$keys = "";
		foreach ($instance as $key => $value)
			if (isset($value))
				$keys .= ($keys ? ", " : "") . $key;
		return $keys;
	}

	/**
	 * The values present in a record, quoted, as a string suitable for use in SQL queries
	 * @param Entity $instance
	 * @return string
	 */
	public function values(Entity $instance): string {
		$values = "";
		foreach ($instance as $key => $value)
			if (isset($value))
				$values .= ($values ? ", " : "") . (is_float($value) && is_nan($value) ? "NULL" : $this->quote($value));
		return $values;
	}

	/**
	 * Inserts the object into the DB table
	 * @return string The insert ID
	 */
	public function insert($data): string {
		$class = static::ENTITY;
		$instance = ($data instanceof $class ? $data : new $class($data));
		$sql = "INSERT INTO ".static::TABLE." (".$this->keys($instance).") VALUES (".$this->values($instance).")";
		$this->pdo->exec($sql);
		return $this->pdo->lastInsertId();
	}

	/**
	 * Inserts the object into the DB table, updating the existing record if there's a duplicate key
	 * @return int The number of rows affected
	 */
	public function upsert($data): int {
		$class = static::ENTITY;
		$instance = ($data instanceof $class ? $data : new $class($data));
		$driver = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
		$sql = "INSERT INTO ".static::TABLE."(".$this->keys($instance).") VALUES (".$this->values($instance).")";
		if ($driver == 'sqlite')
			$sql .= " ON CONFLICT DO UPDATE SET ".$this->quote($instance);
		else
			$sql .= " ON DUPLICATE KEY UPDATE ".$this->quote($instance);
		return $this->pdo->exec($sql);
	}

	/**
	 * Inserts the object into the DB table, fully replacing the existing record if there's a duplicate key
	 * @return int The number of rows affected
	 */
	public function replace($data): int {
		$class = static::ENTITY;
		$instance = ($data instanceof $class ? $data : new $class($data));
		$sql = "REPLACE INTO ".static::TABLE."(".$this->keys($instance).") VALUES (".$this->values($instance).")";
		return $this->pdo->exec($sql);
	}

	/**
	 * Updates the record(s) in the DB table, found by the ID(s) from the optional parameter or the object itself
	 * @param string|string[]|null $id Optional value looked up in the key column
	 * @return int The number of rows affected
	 */
	public function update($id, $data): int {
		$class = static::ENTITY;
		$instance = ($data instanceof $class ? $data : new $class($data));
		$sql = $this->quote($instance);
		if (is_array($id) && !$id || !$sql)
			return 0;
		$sql = "UPDATE ".static::TABLE." SET $sql WHERE ".static::KEY." IN (".$this->quote($id).")";
		return $this->pdo->exec($sql);
	}

	/**
	 * Deletes DB record(s) identified by the ID(s)
	 * @param string|string[] $id
	 * @return int The number of rows affected
	 */
	public function delete($id): int {
		if (is_array($id) && !$id)
			return 0;
		$sql = "DELETE FROM ".static::TABLE." WHERE ".static::KEY." IN (".$this->quote($id).")";
		return $this->pdo->exec($sql);
	}

	/**
	 * Returns the total record count in the table
	 * @return int
	 */
	public function count(): int {
		$sql = "SELECT COUNT(*) FROM ".static::TABLE;
		$count = $this->pdo->query($sql)->fetchColumn();
		return $count;
	}

	/**
	 * Tells whether any records exist having given ID(s)
	 * @param string|string[] $id ID(s) to look for
	 * @return boolean
	 */
	public function exists($id): bool {
		$sql = "SELECT 1 FROM ".static::TABLE." WHERE ".static::KEY." IN (".$this->quote($id).") LIMIT 1";
		return $this->pdo->query($sql)->fetchColumn();
	}

	/**
	 * Fetches one or more records by their ID(s)
	 * @param string|string[] $id The ID(s) to look for
	 * @return object|object[]|null The record(s)
	 */
	public function get($id) {
		$sql = "SELECT * FROM ".static::TABLE." WHERE ".static::KEY." IN (".$this->quote($id).")";
		if (!is_array($id))
			return $this->fetch($sql);
		elseif ($id)
			return $this->fetchAll($sql);
		return [];
	}

	/**
	 * Fetches all records, optionally paged
	 * @param int $limit Optionally, maximum number of records
	 * @param string|null $after_id Optionally, the last ID from the previous request
	 * @param int $page
	 * @return array
	 */
	public function getAll(int $limit = 0, ?string $after_id = null, int $page = 0): array {
		$sql = "SELECT * FROM ".static::TABLE
				.(isset($after_id) ? " WHERE ".static::KEY.($limit > 0 ? " > ":" < ").$this->quote($after_id) : "")
				." ORDER BY ".(static::ORDER ?? static::KEY).($limit >= 0 ? " ASC":" DESC")
				.($limit ? " LIMIT ".($page ? abs($page*$limit).',':'') . abs($limit) : "");
		return $this->fetchAll($sql);
	}

	/**
	 * Fetches a record by direct SQL query
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return null|Entity The record or null
	 */
	public function fetch($q, ?array $params = null): ?Entity {
		if (!$q instanceof PDOStatement)
			$q = $this->query($q, $params);
		return $q->fetchObject(static::ENTITY) ?: null;
	}

	/**
	 * Fetches records by direct SQL query, optionally indexed by a column
	 * @param PDOStatement|string $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @param string|null $keyColumn
	 * @return object[] The records
	 */
	public function fetchAll($q, ?array $params = null, ?string $keyColumn = null): array {
		if (!$q instanceof PDOStatement)
			$q = $this->query($q, $params);
		if (!$keyColumn)
			return $q->fetchAll(PDO::FETCH_CLASS, static::ENTITY);
		for ($objects = []; ($o = $q->fetchObject(static::ENTITY)); $objects[$o->$keyColumn] = $o);
		return $objects;
	}

	/**
	 * Fetches records utilizing Generator
	 * @param string|PDOStatement $q The query as an SQL string or PDOStatement
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @param string|null $keyColumn
	 * @return Generator<array>
	 */
	public function generateAll($q, ?array $params = null, ?string $keyColumn = null): Generator {
		if (!$q instanceof PDOStatement)
			$q = $this->query($q, $params);
		while (($o = $q->fetchObject(static::ENTITY)))
			if ($keyColumn)
				yield $o->$keyColumn => $o;
			else
				yield $o;
	}

	/**
	 * Fetches a single result (column) by direct SQL query
	 * @param string|PDOStatement $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string|null
	 */
	public function result($q, ?array $params = null): ?string {
		if (!$q instanceof PDOStatement)
			$q = $this->query($q, $params);
		$result = $q->fetchColumn();
		return ($result !== false ? $result : null);
	}

	/**
	 * Fetches a column by direct SQL query
	 * @param string|PDOStatement $q The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return string[]
	 */
	public function allResults($q, ?array $params = null): array {
		if (!$q instanceof PDOStatement)
			$q = $this->query($q, $params);
		return $q->fetchAll(PDO::FETCH_COLUMN);
	}

	/**
	 * Performs an SQL query, optionally a prepared statement
	 * @param string $sql The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return PDOStatement
	 */
	protected function query(string $sql, ?array $params = null) {
		if ($params)
			$stmt = $this->pdo->prepare($sql);
		else
			$stmt = $this->pdo->query($sql);
		if ($params)
			$stmt->execute($params);
		return $stmt;
	}

	/**
	 * Executes an SQL query that returns no results
	 * @param string $sql The query
	 * @param array|null $params Optional parameters for a prepared statement [optional]
	 * @return int The number of rows affected
	 */
	protected function exec(string $sql, ?array $params = null): int {
		if ($params) {
			$stmt = $this->pdo->prepare($sql);
			$stmt->execute($params);
			$affected = $stmt->rowCount();
		} else
			$affected = $this->pdo->exec($sql);
		return $affected;
	}

	/**
	 * Escapes and quotes a string
	 * @param string|string[]|object $str
	 * @return string
	 */
	protected function quote($str): string {
		if (is_object($str)) {
			$sql = '';
			foreach ($str as $column => $value)
				if (isset($str->$column))
					$sql .= ($sql ? ', ':'') . "`$column` = ".(is_float($value) && is_nan($value) ? 'NULL' : $this->quote($value)); // TODO
		} elseif (is_array($str))
			$sql = implode(',', array_map([$this, 'quote'], $str));
		else
			$sql = $this->pdo->quote($str ?? '');
		return $sql;
	}

	/**
	 * Starts a new transaction
	 */
	protected function begin() {
		if ($this->transactionLevel++ == 0)
			$this->pdo->beginTransaction();
	}

	/**
	 * Commits the current transaction
	 */
	protected function commit() {
		if ($this->transactionLevel == 1)
			$this->pdo->commit();
		$this->transactionLevel = max(0, $this->transactionLevel - 1);
	}

}
