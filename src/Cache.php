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
		$this->set($filename, $data, $ttl);
	}
	
	function set($filename, $data, $ttl = 0) {
		if (!($f = fopen($path = $this->directory.$filename.'.php', "w")))
			return false;
		flock($f, LOCK_EX);
		$success = fwrite($f, '<?php $value = '.var_export($data, true).';');
		flock($f, LOCK_UN);
		fclose($f);
		@chmod($path, 0666);
		if ($success)
			@touch($path, time() + ($ttl ? $ttl : $this->ttl));
		return true;
	}

	function get($filename) {
		if (@filemtime($path = $this->directory.$filename.'.php') >= time())
			@include $path;
		return isset($value) ? $value : false;
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