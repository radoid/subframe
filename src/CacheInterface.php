<?php
namespace Subframe;

/**
 * Implements a filesystem caching mechanism
 * @package Subframe PHP Framework
 */
interface CacheInterface {

	/**
	 * Checks whether an item exists in the cache and is not expired
	 * @param string $name The item's filename
	 * @return bool
	 */
	public function has(string $name): bool;

	/**
	 * Stores an item
	 * @param string $name The item's filename
	 * @param string $content The content
	 * @param int|null $ttl Duration in seconds, or default time will be used
	 * @return bool true on success or false on failure
	 */
	public function set(string $name, string $content, ?int $ttl = null): bool;

	/**
	 * Retrieves an item, if it exists and is not expired, or the default value otherwise
	 * @param string $name The item's filename
	 * @return mixed|null The content on success or null on failure or expiry
	 */
	public function get(string $name, $default = null);

	/**
	 * Deletes the item
	 * @param string $name The item's filename
	 * @return bool true on success or false on failure
	 */
	public function delete(string $name): bool;

	/**
	 * Deletes all items or just those having a prefix in the name
	 * @param string $prefix The prefix; empty string will delete all items
	 * @return bool true on success or false on failure
	 */
	public function clear(string $prefix = ''): bool;

	/**
	 * Returns the item's expiry time (Unix timestamp)
	 * @param string $name
	 * @return int|null Timestamp or null on failure
	 */
	public function getExpiryTime(string $name): ?int;

}
