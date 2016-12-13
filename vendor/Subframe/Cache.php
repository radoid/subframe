<?php
/**
 * Implements the caching mechanism
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Cache
{
	private $directory, $ttl;

	function __construct($directory, $ttl = 86400) {
		$this->directory = rtrim($directory, "/") . "/";
		$this->ttl = $ttl;
	}

	function ready($filename, $lastmodified = 0) {
		if (@filemtime($this->directory . $filename) > time())
			if ($lastmodified)
				return ($lastmodified >= @filectime($this->directory . $filename));
			else
				return true;
		return false;
	}

	function dump($filename) {
		return (readfile($this->directory . $filename) > 0);
	}

	function serve($filename, $lastmodified = 0) {
		if (@filemtime($this->directory . $filename) > time()) {
			if ($lastmodified && $lastmodified >= @filectime($this->directory . $filename))
				return true;
			else
				return (readfile($this->directory . $filename) > 0);
		} else
			;
		//@unlink ($this->directory.$filename);  // TODO potrebno?
		return false;
	}

	function put($filename, $data, $ttl = 0) {
		if (!is_string($data))
			$data = serialize($data);
		if (!($f = fopen($this->directory . $filename, "w")))
			return false;
		flock($f, LOCK_EX);
		$success = fwrite($f, $data);
		flock($f, LOCK_UN);
		fclose($f);
		@chmod($this->directory . $filename, 0666);
		if ($success)
			@touch($this->directory . $filename, time() + ($ttl ? $ttl : $this->ttl));
		return true;
	}

	function get($filename) {
		if (@filemtime($this->directory . $filename) >= time())
			return file_get_contents($this->directory . $filename);
		return false;
	}

	function serialize($filename, $data, $ttl = 0) {
		return serialize($this->put($filename, $data, $ttl));
	}

	function unserialize($filename) {
		return @unserialize($this->get($filename));
	}

	function clean($name = "") {
		return $this->delete($name);
	}

	function delete($name = "") {
		foreach (scandir($this->directory) as $filename)
			if (is_file($this->directory . $filename))
				if ($name === "" || ($name && strpos($filename, $name) === 0) || @filemtime($this->directory . $filename) < time())
					if (!@unlink($this->directory . $filename))
						return false;
		return true;
	}

}