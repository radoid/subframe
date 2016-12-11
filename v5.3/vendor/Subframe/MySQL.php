<?php
/**
 * Database access layer using standard MySQLi driver
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class MySQL
{
	private $username, $password, $database, $hostname, $port, $link;

	function __construct($database, $username = "", $password = "", $hostname = "localhost", $port = 3306) {
		$this->username = $username;
		$this->password = $password;
		$this->database = $database;
		$this->hostname = $hostname;
		$this->port = $port;
	}

	function connect() {
		if (!$this->link)
			$this->link = @new \mysqli ($this->hostname, $this->username, $this->password, $this->database, $this->port);
		if (mysqli_connect_errno())
			$this->link = null;
		else
			$this->link->query("SET NAMES utf8mb4");
		return $this->link;
	}

	function isConnected() {
		return (bool)$this->link;
	}

	function query($sql) {
		if (!$this->link)
			$this->connect();
		if ($this->link)
			if (($result = $this->link->query($sql)))
				return $result;
			elseif (defined('DEBUG') && DEBUG)
				trigger_error('<h3>'.$this->error().'</h3><pre>'.htmlspecialchars(var_export(debug_backtrace(), true)));  // TODO
		return false;
	}

	function fetch_assoc($q) {
		if (is_string($q))
			$q = $this->query($q);
		if ($q instanceof \mysqli_result)
			return $q->fetch_assoc();
		return false;
	}

	function fetch_all_assoc($q) {
		if (is_string($q))
			$q = $this->query($q);
		if (!$q instanceof \mysqli_result)
			return false;
		for ($rows = []; ($r = $q->fetch_assoc()); $rows[] = $r) ;
		return $rows;
	}

	function fetch_row($q) {
		if (is_string($q))
			$q = $this->query($q);
		if ($q instanceof \mysqli_result)
			return $q->fetch_row();
		return false;
	}

	function fetch_object($q, $classname = "stdClass") {
		if (is_string($q))
			$q = $this->query($q);
		if ($q instanceof \mysqli_result)
			return $q->fetch_object($classname);
		return false;
	}

	function fetch_all_objects($q, $classname = "stdClass", $primary = "") {
		if (is_string($q))
			$q = $this->query($q);
		if (!$q instanceof \mysqli_result)
			return false;
		$objects = [];
		while (($o = $q->fetch_object($classname)))
			if ($primary)
				$objects[$o->$primary] = $o;
			else
				$objects[] = $o;
		return $objects;
	}

	function result($q) {
		if (is_string($q))
			$q = $this->query($q);
		if ($q instanceof \mysqli_result && ($r = $q->fetch_row()))
			return $r[0];
		return ($q === false ? false : null);
	}

	function all_results($q, $column = '', $primary = '') {
		if (is_string($q))
			$q = $this->query($q);
		if (!$q instanceof \mysqli_result)
			return false;
		for ($results = []; ($r = ($column ? $q->fetch_assoc() : $q->fetch_row()));)
			if ($column && $primary)
				$results[$r[$primary]] = ($column ? $r[$column] : $r[0]);
			else
				$results[] = ($column ? $r[$column] : $r[0]);
		return $results;
	}

	function num_rows($q) {
		if (is_string($q))
			$q = $this->query($q);
		if ($q instanceof \mysqli_result)
			return $q->num_rows;
		return false;
	}

	function affected_rows() {
		return ($this->link ? $this->link->affected_rows : 0);
	}

	function insert_id() {
		return ($this->link ? $this->link->insert_id : 0);
	}

	function escape($s) {
		if (!$this->link)
			$this->connect();
		if (is_array($s))
			return array_map([$this, 'escape'], $s);
		return ($this->link ? $this->link->real_escape_string($s) : addslashes($s));
	}

	function quote($s) {
		if (is_array($s))
			return implode(',', array_map([$this, 'quote'], $s));
		return "'" . $this->escape($s) . "'";
	}

	function error() {
		return ($this->link ? $this->link->error : (mysqli_connect_errno() ? mysqli_connect_error() : ""));
	}

	protected $translevel = 0;

	function begin() {
		if (!$this->link)
			$this->connect();
		if ($this->translevel++)
			return ($this->link && $this->link->query("SAVEPOINT LEVEL" . ($this->translevel - 1)));
		else
			return ($this->link && $this->link->autocommit(false));
	}

	function commit() {
		if (--$this->translevel)
			return ($this->link && $this->link->query("RELEASE SAVEPOINT LEVEL$this->translevel"));
		else
			return ($this->link && $this->link->commit() && $this->link->autocommit(true));
	}

	function rollback() {
		if (--$this->translevel)
			return ($this->link && $this->link->query("ROLLBACK TO SAVEPOINT LEVEL$this->translevel"));
		else
			return $this->link && $this->link->rollback() && $this->link->autocommit(true);
	}

	function version() {
		if (!$this->link)
			$this->connect();
		return ($this->link ? $this->link->get_server_info() : false);
	}

}