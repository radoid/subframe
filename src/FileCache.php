<?php
namespace Subframe;

/**
 * Implements a simple filesystem caching mechanism
 * @package Subframe PHP Framework
 */
class FileCache implements CacheInterface {

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
	public function __construct(string $directory, int $ttl = 86400) {
		$this->directory = rtrim($directory, "/") . "/";
		$this->ttl = $ttl;
	}

	/**
	 * Full path to the item's corresponding file
	 */
	protected function getPath(string $name): string {
		return $this->directory.strtr($name, '/', '_');
	}

	/**
	 * Item's expiry time (Unix timestamp)
	 * @param string $name
	 * @return int|null Timestamp or null on failure
	 */
	public function getExpiryTime(string $name): ?int {
		$mtime = @filemtime($this->getPath($name));

		return $mtime ?: null;
	}

	/**
	 * Checks whether an item exists in the cache and is not expired
	 * @param string $name The item's filename
	 * @return bool
	 */
	public function has(string $name): bool {
		$mtime = @filemtime($this->getPath($name));
		if ($mtime !== false && $mtime < time())
			$this->delete($name);
		return ($mtime > time());
	}

	/**
	 * Stores an item, of any type
	 * @param string $name The item's filename
	 * @param mixed $content The content
	 * @param int|null $ttl Duration in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function set(string $name, $content, ?int $ttl = null): bool {
		$path = $this->getPath($name);
		if (!is_string($content))
			$content = serialize($content);
		$isSuccess = (file_put_contents($path, $content, LOCK_EX) !== false)
			and touch($path, time() + ($ttl ?? $this->ttl));
		return $isSuccess;
	}

	/**
	 * Retrieves an item, if it exists and is not expired, or the default value otherwise
	 * @param string $name The item's filename
	 * @return mixed|null The content on success or null on failure or expiry
	 */
	public function get(string $name, $default = null) {
		if ($this->has($name))
			if (($content = file_get_contents($this->getPath($name))) !== false) {
				if (@$content[1] == ':' || $content == 'N;')
					if (($unserialized = @unserialize($content)) !== false || $content == 'b:0;')
						return $unserialized;
				return $content;
			}
		return $default;
	}

	/**
	 * Deletes the item
	 * @param string $name The item's filename
	 * @return bool true on success or false on failure
	 */
	public function delete(string $name): bool {
		$isSuccess = unlink($this->getPath($name));
		return $isSuccess;
	}

	/**
	 * Deletes all items or just those having a prefix in the name
	 * @param string $prefix The prefix; empty string will delete all items
	 * @return bool true on success or false on failure
	 */
	public function clear(string $prefix = ''): bool {
		foreach (scandir($this->directory) as $filename)
			if ($filename[0] != '.')
				if (strpos($filename, $prefix) === 0 || @filemtime($this->directory . $filename) < time())
					if (!unlink($this->directory . $filename))
						return false;
		return true;
	}

	/**
	 * Deletes all expired items
	 * @return bool true on success or false on failure
	 */
	public function purge(): bool {
		foreach (scandir($this->directory) as $filename)
			if (is_file($this->directory . $filename))
				if (@filemtime($this->directory . $filename) < time())
					if (!unlink($this->directory . $filename))
						return false;
		return true;
	}

}
