<?php
namespace Subframe;

use Exception;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionParameter;

/**
 * Inversion-of-control container
 */
class Container {

	private static array $singletons = [];
	private static array $multitons = [];

	/**
	 * Registers a new service/object under the given name
	 * @param string $name Name under which a service is registered
	 * @param object|callable $object An object or callable for generating the object
	 * @param bool $isSingleton Whether to always retrieve the same object when generating
	 */
	public static function set(string $name, $object, bool $isSingleton = true): void {
		if ((is_string($object) || is_callable($object)) && !$isSingleton)
			self::$multitons[$name] = $object;
		else
			self::$singletons[$name] = $object;
	}

	/**
	 * Unregisters any service/object under the given name
	 * @param string $name Name under which a service is registered
	 */
	public static function unset(string $name): void {
		unset(self::$multitons[$name]);
		unset(self::$singletons[$name]);
	}

	/**
	 * Retrieves a service/object registered under the given name, or null if there's none
	 * @param string $name Name under which a service is registered
	 * @return object|null The found object or null
	 */
	public static function get(string $name) {
		if (($instance = self::$singletons[$name] ?? null))
			$isSingleton = true;
		else {
			$instance = self::$multitons[$name] ?? null;
			$isSingleton = false;
		}
		
		if (is_callable($instance)) {
			$instance = $instance();
			if ($isSingleton)
				self::$singletons[$name] = $instance;
		}

		return $instance ?? null;
	}

	/**
	 * Resolves the service/object, instantiates it if needed and returns it, or throws an exception if that isn't possible
	 * @param string $name Name under which a service is registered
	 * @param array $classParams In case a class needs to be instantiated, optional parameters for the constructor
	 * @return object The found or instantiated object
	 * @throws Exception
	 */
	public static function make(string $name, array $classParams = []): object {
		$instance = self::get($name);
		if (!$instance)
			if (class_exists($name))
				$instance = self::instantiate($name, $classParams);
			else
				throw new Exception("Container: No class or object for \"$name\"", 500);
		
		return $instance;
	}

	/**
	 * Instantiates the given class, injecting any parameters that are needed and aren't provided
	 * @param string $class The class name
	 * @param array $params Optional parameters for the constructor
	 * @return object The instantiated object
	 * @throws Exception
	 */
	public static function instantiate(string $class, array $params = []): object {
		if (method_exists($class, '__construct'))
			$params = self::injectParameters([$class, '__construct'], $params);
		$instance = new $class(...$params);

		return $instance;
	}

	/**
	 * Makes a call with the given callable while injecting any needed parameters
	 * @param callable|array $callable The callable
	 * @param array $params The parameters for the callable
	 * @param array $classParams In case a class needs to be instantiated for the callable, the parameters for its constructor
	 * @return mixed The return value of the call
	 * @throws Exception
	 */
	public static function call($callable, array $params = [], array $classParams = []) {
		if (is_array($callable) && is_string($callable[0]))
			$callable[0] = Container::make($callable[0], $classParams);

		$params = self::injectParameters($callable, $params);

		$result = call_user_func_array($callable, $params);

		return $result;
	}

	/**
	 * Checks whether a call to the given callable can be made using the provided parameters and injecting those that are missing
	 * @param callable|array $callable The callable
	 * @param array $provided The provided parameters for the callable
	 * @return bool True if the call can be made, otherwise false
	 * @throws Exception
	 */
	public static function canInjectParameters($callable, array $provided = []): bool {
		$r = (is_array($callable) ? new ReflectionMethod($callable[0], $callable[1]) : new ReflectionFunction($callable));
		$params = $r->getParameters();
		$providedCount = count($provided);

		if ($providedCount > count($params))
			return false;
		
		$i = 0;
		foreach ($params as $param) {
			$paramType = $param->getType();
			$isInjectable = $paramType && !$paramType->isBuiltin();
			$isCompatible = ($i < $providedCount && self::isCompatibleType($param, $provided[$i]));
			if ($isCompatible)  // if next provided is compatible, can be consumed
				$i++;
			elseif (!$isInjectable)
				if (!$param->isOptional())
					return false;
		}
		
		if ($i < $providedCount)
			return false;

		return true;
	}

	/**
	 * Collects the parameters for the given callable by injecting those that are missing
	 * @param callable|array $callable The callable
	 * @param array $provided The provided parameters
	 * @return array The complete array of parameters
	 * @throws Exception
	 */
	public static function injectParameters($callable, array $provided = []): array {
		$r = (is_array($callable) ? new ReflectionMethod($callable[0], $callable[1]) : new ReflectionFunction($callable));
		$params = $r->getParameters();
		$providedCount = count($provided);

		if ($providedCount > count($params))
			throw new Exception("Container: Excessive parameters for \"$r->name\"()", 500);
		
		$i = 0;
		$delivered = [];
		foreach ($params as $param) {
			$paramType = $param->getType();
			$paramTypeName = ($paramType ? $paramType->getName() : null);
			$isParamScalar = !$paramType || $paramType->isBuiltin();

			$isCompatible = ($i < $providedCount && self::isCompatibleType($param, $provided[$i]));
			if ($isCompatible)  // if next provided is compatible, can be consumed
				$delivered[] = $provided[$i++];
			elseif (!$isParamScalar)
				if (!$param->isOptional())  // if next must be injected
					$delivered[] = Container::make($paramTypeName);
				else  // if next can be injected if defined
					$delivered[] = Container::get($paramTypeName) ?? $param->getDefaultValue();
			else
				if ($param->isOptional())
					$delivered[] = $param->getDefaultValue();
				else
					throw new Exception("Container: Cannot inject missing parameter of type \"$paramTypeName\"", 500);
		}
		
		if ($i < $providedCount)
			throw new Exception("Container: Excessive parameters for \"$r->name\"()", 500);
		
		return $delivered;
	}

	private static function isCompatibleType(ReflectionParameter $param, $value): bool {
		$type = $param->getType();
		$typeName = ($type ? $type->getName() : null);
		return !$type
				|| $param->allowsNull() && $value === null
				|| $type->isBuiltin() == !is_object($value) // TODO
						&& !(is_string($value) && (
								($typeName == 'int' && !ctype_digit($value))
								|| ($typeName == 'float' && !is_numeric($value))
								|| ($typeName == 'bool' && !in_array($value, ['0', '1']))));
	}

}
