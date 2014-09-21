<?php
/**
 * Implements the data model functionality
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Model
{
	const PREFIX = "", TABLE = "", KEY = "";

	static $db, $cache;

	const Null = NAN;

	function __construct($data = null, $force = false) {
		if (is_array($data)) {
			foreach (($force ? $data : $this) as $key => $value)
				if (isset ($data[$key]))
					$this->$key = $data[$key];
		} elseif (is_object($data))
			foreach (($force ? $data : $this) as $key => $value)
				if (isset ($data->$key))
					$this->$key = $data->$key;
	}

	function merge($data, $force = false) {
		foreach ($data as $key => $value)
			if (isset($value) || $force)
				$this->$key = $value;
		return $this;
	}

	public function trim() {
		foreach ($this as $key => $value)
			if (!isset ($this->$key))
				unset ($this->$key);
		return $this;
	}

	static function column(array $objects, $column, $key = '') {
		$array = array();
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = $object->$column;
		return $array;
	}

	function __toString() {
		$sql = "";
		foreach ($this as $column => $value)
			if (isset ($this->$column))
				$sql = ($sql ? "$sql, " : "")."$column=".(is_double($value) && !is_finite($value) ? "NULL" : "'".(self::$db ? self::$db->escape($value) : $value)."'");
		return $sql;
	}

	function keys() {
		$keys = "";
		foreach ($this as $key => $value)
			if (isset ($this->$key))
				$keys .= ($keys ? ", " : "") . $key;
		return $keys;
	}

	function values() {
		$values = "";
		foreach ($this as $key => $value)
			if (isset ($this->$key))
				$values .= ($values ? ", " : "") . (is_double($value) && is_nan($value) ? "NULL" : "'".(self::$db ? self::$db->escape($value) : addslashes($value))."'");
		return $values;
	}

	static function isNull($value) {
		return (is_double($value) && is_nan($value));
	}

	static function __callstatic($name, $args) {
		if (self::$db && method_exists(self::$db, $name))
			return call_user_func_array(array(self::$db, $name), $args);
		trigger_error("Sorry, unexpected error. Method $name not found.");
		return false;
	}

	static function get($id) {
		if (is_array($id))
			return ($id ? self::$db->fetch_all_objects("SELECT * FROM ".static::PREFIX.static::TABLE." WHERE ".static::KEY." IN ('".implode("','", self::$db->escape($id))."')", get_called_class()) : array());
		else
			return self::$db->fetch_object("SELECT * FROM ".static::PREFIX.static::TABLE." WHERE ".static::KEY."='".self::$db->escape($id)."'", get_called_class());
	}

	static function getAll($limit = 0, $page = 0) {
		$limit = ($limit ? " LIMIT ".($page ? ($page * $limit).", " : "") . +$limit : "");
		return self::$db->fetch_all_objects("SELECT * FROM ".static::PREFIX.static::TABLE." ORDER BY ".static::KEY." DESC" . $limit, get_called_class());
	}

	function insert() {
		if (self::$db->query("INSERT INTO ".static::PREFIX.static::TABLE."(".$this->keys().") VALUES (".$this->values().")"))
			return self::$db->insert_id();
		return false;
	}

	function replace() {
		return self::$db->query("REPLACE INTO ".static::PREFIX.static::TABLE."(".$this->keys().") VALUES (".$this->values().")");
	}

	function update($id = null) {
		$id = (isset($id) ? $id : $this->{static::KEY});
		if (is_array($id))
			return ($id ? self::$db->query("UPDATE ".static::PREFIX.static::TABLE." SET $this WHERE ".static::KEY." IN ('".implode("','", self::$db->escape($id))."')") : true);
		else
			return self::$db->query("UPDATE ".static::PREFIX.static::TABLE." SET $this WHERE ".static::KEY."='".self::$db->escape($id)."'");
	}

	static function delete($id) {
		if (is_array($id))
			return ($id ? self::$db->query("DELETE FROM ".static::PREFIX.static::TABLE." WHERE ".static::KEY." IN ('".implode("','", self::$db->escape($id))."')") : true);
		else
			return self::$db->query("DELETE FROM ".static::PREFIX.static::TABLE." WHERE ".static::KEY."='".self::$db->escape($id)."'");
	}

}