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
	 * Checks whether an item in the cache and is still valid
	 * @param string $filename The file name of the item
	 * @param int $lastModified Optional timestamp of the last modification (to also check whether the content is new, not just valid)
	 * @return bool
	 */
	public function ready($filename, $lastModified = 0) {
		if (@filemtime($this->directory . $filename) > time())
			if ($lastModified)
				return ($lastModified >= @filectime($this->directory . $filename));
			else
				return true;
		return false;
	}

	/**
	 * Outputs the content
	 * @param string $filename The file name with the content
	 * @return int|boolean Number of bytes read/output, or false on error
	 */
	public function dump($filename) {
		return readfile($this->directory . $filename);
	}

	/**
	 * Stores the item under the filename
	 * @param string $filename The filename
	 * @param mixed $value The content, of any type
	 * @param int $ttl Expiry time in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function put($filename, $value, $ttl = 0) {
		if (!($f = fopen($path = $this->directory.$filename.'.php', "w")))
			return false;
		flock($f, LOCK_EX);
		$success = fwrite($f, '<?php $value = '.var_export($value, true).';');
		flock($f, LOCK_UN);
		fclose($f);
		@chmod($path, 0666);
		if ($success)
			@touch($path, time() + ($ttl ? $ttl : $this->ttl));
		return true;
	}

	/**
	 * Retrieves the item stored under the filename, if it exists and is still valid
	 * @param string $filename The filename
	 * @return mixed|bool The content on success or false on failure or expiry
	 */
	public function get($filename) {
		if (@filemtime($path = $this->directory.$filename.'.php') >= time())
			@include $path;
		return isset($value) ? $value : false;
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

}
