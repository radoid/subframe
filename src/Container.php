<?php
namespace Subframe;

use Exception;
use ReflectionClass;

/**
 * Inversion-of-control container
 */
class Container {

	private static $objects = [];

	/**
	 * Registers a new service/object under the given name
	 * @param string $name 
	 * @param mixed $object 
	 * @return void 
	 */
	public static function set(string $name, $object): void {
		self::$objects[$name] = $object;
	}

	/**
	 * Tells whether a service/object is already registered under the given name
	 * @param string $name 
	 * @return bool 
	 */
	public static function has(string $name): bool {
		return key_exists($name, self::$objects);
	}

	/**
	 * Resolves the service/object and returns its singleton object if it was possible to resolve it
	 * @param string $name 
	 * @return null|object 
	 * @throws Exception 
	 * @throws Exception 
	 */
	public static function get(string $name): ?object {
		if (key_exists($name, self::$objects))
			$classOrObject = self::$objects[$name];
		elseif (class_exists($name))
			$classOrObject = $name;
		elseif (interface_exists($name)) {
			foreach (self::$objects as $key => $value)
				if (class_exists($key) && in_array($name, class_implements($key)))
					$classOrObject = $value;
			if (!$classOrObject)
				throw new Exception("No implementation for \"$name\"", 500);
		} else
			throw new Exception("No class or object for \"$name\"", 500);

		if (is_callable($classOrObject)) {
			$instance = $classOrObject();
			self::$objects[$name] = $instance;
		}
		
		if (is_string($classOrObject)) {
			$args = [];
			$r = new ReflectionClass($classOrObject);
			if (($constructor = $r->getConstructor())) {
				$params = $constructor->getParameters();
				foreach ($params as $p)
					if (!$p->isOptional())
						if ($p->getType() && !$p->getType()->isBuiltin())
							$args[] = self::get($p->getType()->getName());
						else
							throw new Exception('Cannot inject '.$p->getName(), 500);
			}
			$instance = $r->newInstanceArgs($args);
			self::$objects[$name] = $instance;
		} else
			$instance = $classOrObject;
		
		return $instance;
	}

}
