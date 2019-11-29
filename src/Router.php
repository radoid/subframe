<?php
namespace Subframe;

use ReflectionClass;

/**
 * Implements the application routing
 * @package Subframe PHP Framework
 */
class Router {

	/**
	 * All defined routes
	 * @var array[]
	 */
	private array $routes = [];


	/**
	 * Adds a route defined with a callable
	 * @param string $method The route's method
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param callable $callable The callable to be executed
	 * @param array $classArgs Optional arguments for the class constructor, if callable is a class method
	 */
	public function addRoute(string $method, string $path, callable $callable, array $classArgs = []): self {
		$this->routes[] = [$method, $path, $callable, $classArgs];

		return $this;
	}

	/**
	 * Adds a namespace with its classes/methods as routes
	 * @param string $namespace The namespace
	 * @param array $classArgs Optional arguments for the class constructor, if callable is a class method
	 */
	public function addNamespace(string $namespace, array $classArgs = []): self {
		$this->routes[] = [null, null, $namespace, $classArgs];

		return $this;
	}

	/**
	 * Tries to dispatch the given request among defined routes
	 * @return bool true if found a route, otherwise false
	 */
	public function dispatch(Request $request): bool {
		foreach ($this->routes as [$method, $path, $action, $classArgs]) {
			if ($method)
				$route = $this->matchRoute($request, $method, $path, $action, $classArgs);
			else
				$route = $this->matchNamespace($request, $action, $classArgs);
			if ($route) {
				[$callable, $args] = $route;
				$result = call_user_func_array($callable, $args);
				if ($result !== false)
					return true;
			}
		}

		return false;
	}

	/**
	 * Tries to match a route to the given request
	 * @param Request $request The request
	 * @param string $method The route's method
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param callable|string $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the constructor of the class
	 * @return ?array Array [callable, args] representing a route
	 */
	private function matchRoute(Request $request, string $method, string $path, $callable, array $classArgs = []): ?array {
		if ($method == $request->getMethod() && preg_match("~^$path$~", $request->getPath(), $matches)) {
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0](...$classArgs);
			return [$callable, array_slice($matches, 1)];
		} else
			return null;
	}

	/**
	 * Tries to match the given namespace to the request
	 * @param Request $request The request
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?array Array [callable, args] representing a route
	 */
	private function matchNamespace(Request $request, string $namespace, array $classArgs = []): ?array {
		$path = trim($request->getPath(), '/');
		$argv = ($path !== '' ? explode('/', $path) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = $this->classCase($argv[$i++])) {}
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = $this->matchClass($found, $request, array_slice($argv, $i), $classArgs)))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = $this->matchClass($found, $request, array_slice($argv, $i), $classArgs)))
						return $route;
		}

		return null;
	}

	/**
	 * Tries to find a route within a class that fits in with the request's arguments
	 * @param string $class The class to find a route in
	 * @param Request $request The incoming request
	 * @param string[] $args The parts of the request's path
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?array Array [class, method, args] representing a route
	 */
	private function matchClass(string $class, Request $request, array $args, array $classArgs = []): ?array {
		$requestMethod = strtolower($request->getMethod());
		$count = count($args);

		// index or methodIndex
		if ($count == 0
			&& (method_exists($class, $fn = "{$requestMethod}Index")
					|| method_exists($class, $fn = 'index')))
			$route = [$fn, []];

		// action or methodAction
		else if ($count > 0
			&& (method_exists($class, $fn = $this->actionCase($requestMethod, $args[0]))
					|| method_exists($class, $fn = $this->actionCase('', $args[0]))))
			$route = [$fn, array_slice($args, 1)];

		// resource+action or resource+methodAction
		else if ($count >= 2
				&& (method_exists($class, $fn = $this->actionCase($requestMethod, $args[1]))
						|| method_exists($class, $fn = $this->actionCase('', $args[1])))) {
			array_splice($args, 1, 1);
			$route = [$fn, $args];

		// resource
		} else if (method_exists($class, $fn = $requestMethod))
			$route = [$fn, $args];

		if (isset($route)) {
			$r = new ReflectionClass($class);
			$m = $r->getMethod($route[0]);
			if ($m->isPublic() && $m->getNumberOfRequiredParameters() <= count($route[1]) && $m->getNumberOfParameters() >= count($route[1]))
				return [[new $class($request, ...$classArgs), $route[0]], $route[1]];
		}

		return null;
	}

	/**
	 * Changes case of the argument as if it was a class name (capital letter on each word boundary)
	 * @param string $arg The argument
	 * @return string The result
	 */
	private function classCase(string $arg): string {
		if (strpbrk($arg, '-.'))
			return strtr(ucwords($arg, '-.'), ['-' => '', '.' => '']);
		return ucfirst($arg);
	}

	/**
	 * Changes case of the argument as if it was an action name (method at the beginning and capital letter on each word boundary)
	 * @param string $method The HTTP request method
	 * @param string $arg The argument
	 * @return string The result
	 */
	private function actionCase(string $method, string $arg): string {
		if (strpbrk($arg, '-.'))
			return strtr(lcfirst($method.ucwords($arg, '-.')), ['-' => '', '.' => '']);
		return ($method ? $method.ucfirst($arg) : $arg);
	}

}
