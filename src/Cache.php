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
	 * Default items' duration in seconds
	 * @var int
	 */
	protected $defaultLifetime;


	/**
	 * The constructor
	 * @param string $directory Filesystem directory for storage
	 * @param int $lifetime Default lifetime in seconds
	 */
	public function __construct(string $directory, int $lifetime = 86400) {
		$this->directory = rtrim($directory, '/') . '/';
		$this->defaultLifetime = $lifetime;
	}

	/**
	 * Returns the default items' duration in seconds
	 */
	public function getLifetime(): int {
		return $this->defaultLifetime;
	}

	/**
	 * Stores the item under the filename
	 * @param string $name
	 * @param string $content
	 * @param int|null $lifetime Duration in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function set(string $name, string $content, int $lifetime = null): bool {
		$path = $this->directory.$name;
		$isDone = file_put_contents($path, $content, LOCK_EX);
		if ($isDone)
			@touch($path, time() + ($lifetime ?: $this->defaultLifetime));
		return $isDone;
	}

	/**
	 * Retrieves the item stored under the filename, if it exists and is still valid
	 * @param string $name The filename
	 * @return string|null The content on success or null on failure or expiry
	 */
	public function get(string $name) {
		if (@filemtime($path = $this->directory.$name) >= time())
			$content = file_get_contents($path);
		return $content ?? null;
	}

	/**
	 * Checks whether an item exists in the cache and is still valid
	 * @param string $name The file name of the item
	 * @return bool
	 */
	public function has(string $name): bool {
		$mtime = @filemtime($this->directory.$name);
		
		return ($mtime > time());
	}

	/**
	 * Returns the item's expiry time (Unix timestamp)
	 * @param string $name
	 * @return int|null Timestamp or null on failure
	 */
	public function getExpiryTime(string $name) {
		$mtime = @filemtime($this->directory.$name);

		return $mtime ?? null;
	}

	/**
	 * Deletes all items having given prefix in the name or being expired
	 * @param string $prefix The prefix of the item's name; empty string will catch all items
	 * @return bool true on success or false on failure
	 */
	public function delete(string $prefix): bool {
		foreach (scandir($this->directory) as $filename)
			if (is_file($this->directory . $filename))
				if (strpos($filename, $prefix) === 0 || @filemtime($this->directory . $filename) < time())
					if (!@unlink($this->directory . $filename))
						return false;
		return true;
	}

	/**
	 * Deletes all expired items
	 * @return bool true on success or false on failure
	 */
	public function prune(): bool {
		return $this->delete(':');
	}
}
