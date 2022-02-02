<?php
namespace Subframe;

/**
 * Implements a simple filesystem caching mechanism
 * @package Subframe PHP Framework
 */
class OpcodeCache extends FileCache {

	protected const BEGIN = '<?php $value = unserialize(';
	protected const END = ');';

	/**
	 * Full path to the item's corresponding file
	 */
	protected function getPath(string $name): string {
		return parent::getPath($name.'.php');
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
		$php = self::BEGIN . var_export(serialize($content), true) . self::END;
		$isSuccess = (file_put_contents($path, $php, LOCK_EX) !== false)
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
			if (include $this->getPath($name))
				return $value ?? null;
		return $default;
	}

}
