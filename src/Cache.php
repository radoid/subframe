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
	public function __construct(string $directory, int $ttl = 86400) {
		$this->directory = rtrim($directory, '/') . '/';
		$this->ttl = $ttl;
	}

	/**
	 * Checks whether an item exists in the cache and is still valid
	 * @param string $name The file name of the item
	 * @param int|null $lastModified Optional timestamp of the last modification (to also check whether the content is new, not just valid)
	 * @return bool
	 */
	public function has(string $name): bool {
		$mtime = @filemtime($this->directory.$name);
		
		return ($mtime > time());
	}

	/**
	 * Outputs the item's content
	 * @param string $name The file name with the content
	 * @return int|boolean Number of bytes read/output, or false on error
	 */
	public function dump(string $name) {
		return readfile($this->directory.$name);
	}

	/**
	 * Stores the item under the filename
	 * @param string $name
	 * @param string $content
	 * @param int|null $ttl Duration in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function put(string $name, string $content, ?int $ttl = null): bool {
		$path = $this->directory.$name;
		$isDone = file_put_contents($path, $content, LOCK_EX);
		if ($isDone)
			@touch($path, time() + ($ttl ?: $this->ttl));
		return $isDone;
	}

	/**
	 * Retrieves the item stored under the filename, if it exists and is still valid
	 * @param string $name The filename
	 * @return string|null The content on success or null on failure or expiry
	 */
	public function get(string $name): ?string {
		if (@filemtime($path = $this->directory.$name) >= time())
			$content = file_get_contents($path);
		return $content ?? null;
	}

	/**
	 * Deletes all items having given prefix or being expired
	 * @param string|null $prefix The prefix; empty string will catch all items
	 * @return bool true on success or false on failure
	 */
	public function delete(?string $prefix = null): bool {
		foreach (scandir($this->directory) as $filename)
			if (is_file($this->directory . $filename))
				if ($prefix === null || strpos($filename, $prefix) === 0 || @filemtime($this->directory . $filename) < time())
					if (!@unlink($this->directory . $filename))
						return false;
		return true;
	}

	/**
	 * Deletes all expired items
	 * @return bool true on success or false on failure
	 */
	public function purge(): bool {
		return $this->delete(false);
	}

	/**
	 * Returns the item's expiry time (Unix timestamp)
	 * @param string $name
	 * @return int|null Timestamp or null on failure
	 */
	public function getTime(string $name): ?int {
		$mtime = @filemtime($this->directory.$name);

		return $mtime ?? null;
	}

	/**
	 * Stores the value of any type, var_export-ed
	 * @param string $name
	 * @param mixed $data
	 * @param ?int $ttl Optional duration in seconds, or default duration will be used
	 * @return bool true on success or false on failure
	 */
	public function export(string $name, $data, ?int $ttl = null): bool {
		$content = '<?php return '.var_export($data, true).';';
		return $this->put("$name.php", $content, $ttl);
	}

	/**
	 * Retrieves the var_export-ed data
	 * @param string $name
	 * @return mixed|null The data, or null on error
	 */
	public function import(string $name) {
		if (@filemtime($path = $this->directory.$name.'.php') >= time())
			$data = @(include $path);
		return $data ?? null;
	}

	/**
	 * Stores the value of any type, serialized
	 * @param string $name
	 * @param mixed $data
	 * @param ?int $ttl Optional duration in seconds, or default duration will be used
	 * @return bool true on success or false on failure
	 */
	public function serialize(string $name, $data, ?int $ttl = null): bool {
		$content = serialize($data);
		return $this->put($name, $content, $ttl);
	}

	/**
	 * Retrieves and unserializes the data
	 * @param string $name
	 * @return mixed|null The data, or null on error
	 */
	public function unserialize(string $name) {
		if (@filemtime($this->directory.$name) >= time())
			$data = $this->get($name);
		return isset($data) ? unserialize($data) : null;
	}

	/**
	 * Whether the cache holds the item and it is still valid
	 * @param string $name
	 * @return bool
	 */
	public function hasExported(string $name): bool {
		return $this->has("$name.php");
	}

}
