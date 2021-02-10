<?php
namespace Subframe;

use stdClass;

/**
 * Represents a database object, ie. a row in a database table
 * @package Subframe PHP Framework
 */
abstract class Entity extends stdClass {

	/**
	 * Fields that can't be assigned values via the constructor
	 * @var ?array
	 */
	protected const GUARDED = null;

	/**
	 * Entity constructor, optionally initialising from a data array
	 * (only fields that are part of the model are taken)
	 * @param array|null $data Optional associative array with initial data
	 */
	public function __construct(?array $data = null) {
		foreach ($this as $key => $value)
			if (isset($data[$key]) && (!static::GUARDED || !in_array($key, static::GUARDED)))
				$this->$key = $data[$key];
	}

	/**
	 * Instantiates an object of the static class with data from an array
	 * @param $data
	 * @return static
	 */
	public static function __set_state($data) {
		return new static($data);
	}

	/**
	 * Adds all fields from the given object/array to the actual object
	 * @param array|object $data object or associative array with data
	 * @return $this
	 */
	public function merge($data) {
		foreach ($data as $key => $value)
			$this->$key = $value;
		return $this;
	}

	/**
	 * Removes null fields from the object
	 * @return $this
	 */
	public function trim() {
		foreach ($this as $key => $value)
			if (!isset($this->$key))
				unset($this->$key);
		return $this;
	}

	/**
	 * Extracts one field across multiple objects, optionally indexing the resulting array
	 * @param array $objects The objects containing the field
	 * @param string $field The field to be extracted
	 * @param string|null $key Optionally, the field to serve as the array key
	 * @return array
	 */
	public static function column(array $objects, string $field, ?string $key = null): array {
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = $object->$field;
		return $array;
	}

	/**
	 * Extracts multiple fields across single or multiple objects, optionally indexing the result
	 * @param array|object $objects The objects containing the fields
	 * @param array $fields The fields to be extracted
	 * @param string|null $key Optionally, the field to serve as the array key
	 * @return array|object
	 */
	public static function columns($objects, array $fields, ?string $key = null) {
		if (is_object($objects))
			return (object)array_intersect_key((array)$objects, array_flip($fields));
		$array = [];
		$i = 0;
		foreach ($objects as $object)
			$array[$key ? $object->$key : $i++] = (object)array_intersect_key((array)$object, array_flip($fields));
		return $array;
	}

}
