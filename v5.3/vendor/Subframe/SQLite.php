<?php
/**
 * Database access layer using SQLite 2 or 3 driver
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class SQLite
{
	private $dbname, $sqlite2, $sqlite3;

	function __construct($dbname) {
		$this->dbname = $dbname;
		$this->sqlite2 = $this->sqlite3 = false;
		$this->lastresource = false;
	}

	function connect() {
		if (!$this->sqlite2 && !$this->sqlite3 && class_exists('\SQLite3'))
			$this->sqlite3 = new \SQLite3 ($this->dbname);
		if (!$this->sqlite2 && !$this->sqlite3 && function_exists('sqlite_open'))
			$this->sqlite2 = @sqlite_open($this->dbname);
		//if ($this->sqlite3 && !@$this->sqlite3->query ("SELECT * FROM sqlite_master"))
		//	$this->sqlite3 = false;
		if ($this->sqlite3)
			$this->sqlite3->busyTimeout(5000);
		return ($this->sqlite2 || $this->sqlite3);
	}

	function connected() {
		return ($this->sqlite2 || $this->sqlite3);
	}

	function query($sql) {
		$resultless = (stripos(trim($sql), "SELECT ") !== 0);
		$command = ($resultless ? "exec" : "query");
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		if ($this->sqlite2)
			$this->lastresource = sqlite_query($this->sqlite2, $sql);
		elseif ($this->sqlite3)
			$this->lastresource = $this->sqlite3->$command ($sql);
		else
			$this->lastresource = false;
		return $this->lastresource;
	}

	function fetch_row($q = false) {
		if ($q === false)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if ($q)
			return ($this->sqlite2 ? sqlite_fetch_array($q, SQLITE_NUM) : $q->fetchArray(SQLITE3_NUM));
		return false;
	}

	function fetch_assoc($q = false) {
		if ($q === false)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if ($q)
			return ($this->sqlite2 ? sqlite_fetch_array($q, SQLITE_ASSOC) : $q->fetchArray(SQLITE3_ASSOC));
		return false;
	}

	function fetch_all_assoc($q = null) {
		if ($q === null)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if (!$q)
			return false;
		for ($arrays = array(); ($r = ($this->sqlite2 ? sqlite_fetch_array($q, SQLITE_ASSOC) : $q->fetchArray(SQLITE3_ASSOC))) !== false; $arrays[] = $r) ;
		return $arrays;
	}

	function fetch_object($q = null, $classname = "") {
		if ($q === null)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q) and $finalize = true;
		if (!$q)
		return false;
		$o = ($this->sqlite2 ? ($classname ? sqlite_fetch_object($q, $classname) : sqlite_fetch_object($q))
				: (($r = $q->fetchArray(SQLITE3_ASSOC)) ? ($classname ? new $classname ($r) : (object)$r) : false));
		if ($q instanceof SQLite3Result && !empty($finalize))
			$q->finalize();
		return $o;
	}

	function fetch_all_objects($q = null, $classname = "", $primary_key = "") {
		if ($q === null)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if (!$q)
			return false;
		$objects = array();
		while ($this->sqlite2 ? ($classname && ($o = sqlite_fetch_object($q, $classname)) !== false) || (!$classname && ($o = sqlite_fetch_object($q)) !== false)
				: ($o = $q->fetchArray(SQLITE3_ASSOC)) && ($o = ($classname ? new $classname($o) : (object)$o)))
			if ($primary_key)
				$objects[$classname ? $o->$primary_key : $o[$primary_key]] = $o;
			else
				$objects[] = $o;
		if ($q instanceof SQLite3Result)
			$q->finalize();
		return $objects;
	}

	function result($q = null) {
		if ($q === false)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if (!$q)
		return false;
		$r = ($this->sqlite2 ? sqlite_fetch_single($q) : (($r = $q->fetchArray(SQLITE3_NUM)) ? $r[0] : false));
		if ($q instanceof SQLite3Result)
			$q->finalize();
		return $r;
	}

	function num_rows($q = false) {
		if ($q === false)
			$q = $this->lastresource;
		elseif (is_string($q))
			$q = $this->query($q);
		if ($q && $this->sqlite2)
			return sqlite_num_rows($q);
		return false;
	}

	function affected_rows() {
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return ($this->sqlite2 ? sqlite_changes($this->sqlite2) : $this->sqlite3->changes());
	}

	function insert_id() {
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return ($this->sqlite2 ? sqlite_last_insert_rowid($this->sqlite2) : $this->sqlite3->lastInsertRowID());
	}

	function begin() {
		return $this->query("BEGIN");
	}

	function commit() {
		return $this->query("COMMIT");
	}

	function rollback() {
		return $this->query("ROLLBACK");
	}

	function escape($s) {
		if (is_array($s))
			return array_map([$this, 'escape'], $s);
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return ($this->sqlite2 ? sqlite_escape_string($s) : \SQLite3::escapeString($s));
	}

	function quote($s) {
		if (is_array($s))
			return implode(',', array_map([$this, 'quote'], $s));
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return "'".($this->sqlite2 ? sqlite_escape_string($s) : \SQLite3::escapeString($s))."'";
	}

	function error() {
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return ($this->sqlite2 ? sqlite_error_string(sqlite_last_error($this->sqlite2)) : $this->sqlite3->lastErrorMsg());
	}

	function version() {
		if (!$this->sqlite2 && !$this->sqlite3)
			$this->connect();
		return ($this->sqlite2 ? "SQlite 2" : ($this->sqlite3 ? var_export($this->sqlite3->version(), true) : "disconnected"));
	}

	/*
	function db_mysql2sqlite ($sql) {
		$starisql = $sql;
		$sql = str_replace (chr (92) . "'", "<SINGLEQUOTE>", $sql);
		$sql = str_replace (chr (92) . '"', "<DOUBLEQUOTE>", $sql);
		$sql = str_replace (chr (92) . chr(92), "<BACKSLASH>", $sql);
		$sql = eregi_replace ("unix_timestamp *\(\)", "" . time (), $sql);
		if (eregi ("^insert +into +([a-z0-9]+) +set +(.*)", $sql, $regs)) {
			$tablica = $regs[1];
			$lista = $regs[2];
			$stupci = "";
			$podaci = "";
			while ($lista != "") {
				if (eregi ("^([a-z0-9_]+)='([^']*)',? *(.*)", $lista, $regs)) {
					$stupci .= ($stupci != "" ? "," : "") . $regs[1];
					$podaci .= ($podaci != "" ? "," : "") . "'$regs[2]'";
					$lista = $regs[3];
				} elseif (eregi ("^([a-z0-9_]+)=([a-z0-9]+),? *(.*)", $lista, $regs)) {
					$stupci .= ($stupci != "" ? "," : "") . $regs[1];
					$podaci .= ($podaci != "" ? "," : "") . $regs[2];
					$lista = $regs[3];
				} else
					break;
			}
			if ($lista == "" || eregi ("^where ", $lista)) {
				$sql = "INSERT INTO $tablica ($stupci) VALUES ($podaci) $lista";
			}
		}
		$sql = str_replace ("<SINGLEQUOTE>", "''", $sql);
		$sql = str_replace ("<DOUBLEQUOTE>", "\"", $sql);
		$sql = str_replace ("<BACKSLASH>", chr (92), $sql);
		//echo $sql;exit;
		return $sql;
	}
	*/
}