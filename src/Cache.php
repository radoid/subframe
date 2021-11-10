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
	 * Default duration in seconds
	 * @var int
	 */
	protected $ttl;

	/**
	 * The constructor
	 * @param string $directory Storage directory
	 * @param int $ttl Default duration in seconds
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
	 * @param int $ttl Duration in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function put($name, $content, $ttl = 0) {
		$path = $this->directory.$name;
		$isDone = file_put_contents($path, $content, LOCK_EX);
		if ($isDone)
			@touch($path, time() + ($ttl ? $ttl : $this->ttl));
		return $isDone;
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

	/**
	 * Deletes all expired items
	 * @return bool true on success or false on failure
	 */
	public function purge() {
		return $this->delete(false);
	}

	/**
	 * Returns the item's expiry time (Unix timestamp)
	 * @param string $name
	 * @return int|bool Timestamp or false on failure
	 */
	public function getTime($name) {
		$mtime = @filemtime($this->directory.$name);
		return $mtime;
	}

	/**
	 * Stores the value of any type, var_export-ed
	 * @param string $name
	 * @param mixed $data
	 * @param int $ttl Optional duration in seconds, or default duration will be used
	 * @return bool true on success or false on failure
	 */
	public function export($name, $data, $ttl = 0) {
		$content = '<?php return '.var_export($data, true).';';
		return $this->put("$name.php", $content, $ttl);
	}

	/**
	 * Retrieves the var_export-ed data
	 * @param string $name
	 * @return bool|mixed The data, or false on error
	 */
	public function import($name) {
		if (@filemtime($path = $this->directory.$name.'.php') >= time())
			$data = @(include $path);
		return isset($data) ? $data : false;
	}

	/**
	 * Stores the value of any type, serialized
	 * @param string $name
	 * @param mixed $data
	 * @param int $ttl Optional duration in seconds, or default duration will be used
	 * @return bool true on success or false on failure
	 */
	public function serialize($name, $data, $ttl = 0) {
		$content = serialize($data);
		return $this->put($name, $content, $ttl);
	}

	/**
	 * Retrieves and unserializes the data
	 * @param string $name
	 * @return bool|mixed The data, or false on error
	 */
	public function unserialize($name) {
		if (@filemtime($path = $this->directory.$name) >= time())
			$data = $this->get($name);
		return isset($data) ? unserialize($data) : false;
	}

	/**
	 * Whether the cache holds the item and it is still valid
	 * @param string $name
	 * @return bool
	 */
	public function hasExported($name) {
		return $this->has("$name.php");
	}

}
