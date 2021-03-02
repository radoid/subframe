<?php
namespace Subframe;

/**
 * Implements a filesystem caching mechanism
 * @package Subframe PHP Framework
 */
class Cache {

	/**
	 * Directory to hold the files
	 * @var string
	 */
	protected $directory;

	/**
	 * Default expiry time in seconds
	 * @var int
	 */
	protected $ttl;

	/**
	 * The constructor
	 * @param string $directory Storage directory
	 * @param int $ttl Default expiry time in seconds
	 */
	public function __construct($directory, $ttl = 86400) {
		$this->directory = rtrim($directory, "/") . "/";
		$this->ttl = $ttl;
	}

	/**
	 * Checks whether an item exists in the cache and is still valid
	 * @param string $name The file name of the item
	 * @param int $lastModified Optional timestamp of the last modification (to also check whether the content is new, not just valid)
	 * @return bool
	 */
	public function has($name, $lastModified = 0) {
		if (@filemtime($this->directory.$name) > time())
			if ($lastModified)
				return ($lastModified >= @filectime($this->directory.$name));
			else
				return true;
		return false;
	}

	/**
	 * Outputs the item's content
	 * @param string $name The file name with the content
	 * @return int|boolean Number of bytes read/output, or false on error
	 */
	public function dump($name) {
		return readfile($this->directory.$name);
	}

	/**
	 * Stores the item under the filename
	 * @param string $name
	 * @param string $content
	 * @param int $ttl Expiry time in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function put($name, $content, $ttl = 0) {
		if (!($f = fopen($path = $this->directory.$name, "w")))
			return false;
		flock($f, LOCK_EX);
		$success = fwrite($f, $content);
		flock($f, LOCK_UN);
		fclose($f);
		@chmod($path, 0666);
		if ($success)
			@touch($path, time() + ($ttl ? $ttl : $this->ttl));
		return true;
	}

	/**
	 * Retrieves the item stored under the filename, if it exists and is still valid
	 * @param string $name The filename
	 * @return mixed|bool The content on success or false on failure or expiry
	 */
	public function get($name) {
		if (@filemtime($path = $this->directory.$name) >= time())
			$content = file_get_contents($path);
		return isset($content) ? $content : false;
	}

	/**
	 * Deletes all items having given prefix or being expired
	 * @param string $prefix The prefix; empty string will catch all items
	 * @return bool true on success or false on failure
	 */
	public function delete($prefix = "") {
		foreach (scandir($this->directory) as $filename)
			if (is_file($this->directory . $filename))
				if ($prefix === "" || ($prefix && strpos($filename, $prefix) === 0) || @filemtime($this->directory . $filename) < time())
					if (!@unlink($this->directory . $filename))
						return false;
		return true;
	}

	/** Stores the value of any type, serialized
	 * @param string $name
	 * @param string $data
	 * @param int $ttl Expiry time in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function serialize($name, $data, $ttl = 0) {
		$content = var_export($data, true);
		return $this->put("$name.php", $content, $ttl);
	}

	/**
	 * Retrieves and unserializes the data
	 * @param string $name
	 * @return bool|mixed The data, or false on error
	 */
	public function unserialize($name) {
		if (@filemtime($path = $this->directory.$name.'.php') >= time())
			$data = @(include $path);
		return isset($data) ? $data : false;
	}

	/**
	 * Whether the cache holds the item and it is still valid
	 * @param string $name
	 * @return bool
	 */
	public function hasSerialized($name) {
		return $this->has("$name.php");
	}

}
