<?php
namespace Subframe;

/**
 * Implements a caching mechanism using PHP opcode cache
 * @package Subframe PHP Framework
 */
class OpcodeCache {

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
	public function __construct(string $directory, int $lifetime = 600) {
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
	 * Full path to the item's corresponding file
	 */
	protected function getPath(string $name): string {
		return $this->directory . $name . '.php';
	}

	/**
	 * Stores an item under a name
	 * @param string $name The item's name
	 * @param mixed $value The value to store
	 * @param int|null $lifetime Duration in seconds; if not defined, class' default time will be used
	 * @return bool true on success or false on failure
	 */
	public function set(string $name, $value, ?int $lifetime = null): bool {
		$path = $this->getPath($name);
		$content = [
			'expiry' => time() + ($lifetime ?? $this->defaultLifetime),
			'value' => $value,
		];
		$php = '<?php return ' . var_export($content, true) . ';';

		$isSuccess = (file_put_contents($path, $php, LOCK_EX) !== false);

		if ($isSuccess && function_exists('opcache_invalidate'))
			opcache_invalidate($path);

		return $isSuccess;
	}

	/**
	 * Retrieves the item stored under the name, if it exists and is still valid; otherwise null
	 * @param string $name The item's name
	 * @return mixed|null The content on success or null on failure or expiry
	 */
	public function get(string $name) {
		if (file_exists($path = $this->getPath($name))) {
			$content = @include $path;
			if (is_array($content) && isset($content['expiry']) && $content['expiry'] > time())
				return $content['value'];
			else
				@unlink($path);
		}

		return null;
	}

	/**
	 * Deletes all items having given prefix in the name or being expired
	 * @param string $prefix The prefix of the item's name; empty string will catch all items
	 * @return bool true on success or false on failure
	 */
	public function delete(string $prefix): bool {
		foreach (scandir($this->directory) as $filename)
			if (substr($path = $this->directory . $filename, -4) == '.php')
				if (strpos($filename, $prefix) === 0) {
					if (!@unlink($path))
						return false;
				} else
					if (@filemtime($path) < time() - $this->defaultLifetime)
						$this->get(substr($filename, 0, strlen($filename) - 4));
		
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
