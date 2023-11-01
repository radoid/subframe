<?php
namespace Subframe;

use ReflectionClass;
use Throwable;

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
	 * Adds a route defined with 3 components
	 */
	public function addRoute(string $method, string $uri, $action, array $classArgs = []): self {
		$this->routes[] = [$method, $uri, $action, $classArgs];

		return $this;
	}

	/**
	 * Adds a namespace with its classes as routes
	 */
	public function addNamespace(string $namespace, array $classArgs = []): self {
		$this->routes[] = [null, null, $namespace, $classArgs];

		return $this;
	}

	/**
	 * Tries to dispatch the request represented by the global REQUEST_METHOD and REQUEST_URI constants
	 */
	public function handleRequestUri(): bool {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$uri = rawurldecode(strtok($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'], '?'));

		return $this->handle($method, $uri);
	}

	/**
	 * Tries to dispatch the request represented by the global REQUEST_METHOD and PATH_INFO constants
	 */
	public function handlePathInfo(): bool {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$uri = rawurldecode($_SERVER['ORIG_PATH_INFO'] ?? $_SERVER['PATH_INFO'] ?? '/');

		return $this->handle($method, $uri);
	}

	/**
	 * Tries to dispatch the given request among defined routes: executes one and exits if found
	 */
	public function handle(string $requestMethod, string $requestUri): bool {
		foreach ($this->routes as [$method, $uri, $action, $classArgs]) {
			if ($method)
				$route = $this->tryRoute($requestMethod, $requestUri, $method, $uri, $action, $classArgs);
			else
				$route = $this->tryNamespace($requestMethod, $requestUri, $action, $classArgs);
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
	 * Tries to match a route to the given request, and returns the provided callable's response if matched
	 * @param Request $request
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route, without trailing slash or query parameters
	 * @param callable|string $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?array
	 */
	private function tryRoute(string $requestMethod, string $requestUri, string $method, string $uri, $callable, array $classArgs = []): ?array {
		$requestUri = '/' . trim(strtok($requestUri, '?'), '/');
		
		if ($method == $requestMethod && preg_match("~^$uri$~", $requestUri, $matches)) {
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0](...$classArgs);
			$args = array_slice($matches, 1);

			return [$callable, $args];
		} else
			return null;
	}

	/**
	 * Tries to dispatch the given request within a namespace, returns route's Response if found
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	private function tryNamespace(string $requestMethod, string $requestUri, string $namespace, array $classArgs = []): ?array {
		if (($route = $this->findRouteInNamespace($requestMethod, $requestUri, $namespace))) {
			[$class, $action, $args] = $route;
			$instance = new $class(...$classArgs);
			return [[$instance, $action], $args];
		} else
			return null;
	}

	/**
	 * Tries to find a route within a namespace
	 */
	public function findRouteInNamespace(string $requestMethod, string $requestUri, string $namespace): ?array {
		$method = $requestMethod;
		$uri = trim($requestUri, '/');
		$argv = ($uri !== '' ? explode('/', $uri) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = $this->classCase($argv[$i++]));
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = $this->findRouteInClass($found, $method, array_slice($argv, $i))))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = $this->findRouteInClass($found, $method, array_slice($argv, $i))))
						return $route;
		}

		return null;
	}

	/**
	 * Tries to find a route within a class that fits in with the request's arguments
	 * @param string $classname The class in question
	 * @param string $method HTTP method
	 * @param string[] $args The request's arguments
	 * @return string[]|null The action (function) name
	 */
	public function findRouteInClass(string $classname, string $method, array $args): ?array {
		$method = strtolower($method);
		$count = count($args);

		// index or methodIndex
		if ($count == 0
			&& (method_exists($classname, $fn = "{$method}Index")
					|| method_exists($classname, $fn = 'index')))
			$route = [$classname, $fn, []];

		// action or methodAction
		else if ($count > 0
			&& (method_exists($classname, $fn = $this->actionCase($method, $args[0]))
					|| method_exists($classname, $fn = $this->actionCase('', $args[0]))))
			$route = [$classname, $fn, array_slice($args, 1)];

		// resource+action or resource+methodAction
		else if ($count >= 2
				&& (method_exists($classname, $fn = $this->actionCase($method, $args[1]))
						|| method_exists($classname, $fn = $this->actionCase('', $args[1])))) {
			array_splice($args, 1, 1);
			$route = [$classname, $fn, $args];

		// resource
		} else if (method_exists($classname, $fn = $method))
			$route = [$classname, $fn, $args];

		if (isset($route))
			try {
				$r = new ReflectionClass($classname);
				$m = $r->getMethod($route[1]);
				if ($m->isPublic() && $m->getNumberOfRequiredParameters() <= count($route[2]) && $m->getNumberOfParameters() >= count($route[2]))
					return $route;
			}
			catch (Throwable $ignored) {}

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
