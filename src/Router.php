<?php
namespace Subframe;

use ReflectionClass;
use Throwable;

/**
 * Implements the application routing
 * @package Subframe PHP Framework
 */
class Router {

	/** @var string */
	private $method;

	/** @var string */
	private $uri;


	/**
	 * Creates a router for the given request parameters or for the current request
	 * @param string $method HTTP method/verb
	 * @param string $uri requested URI
	 */
	public function __construct(string $method, string $uri) {
		$this->method = $method;
		$this->uri = trim(strtok($uri, '?'), '/');
	}

	/**
	 * Creates a router for the request from the REQUEST_URI constant
	 */
	public static function fromRequestUri(): self {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$requestUri = rawurldecode(strtok($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'], '?'));

		return new Router($method, $requestUri);
	}

	/**
	 * Creates a router for the request from the PATH_INFO constant
	 */
	public static function fromPathInfo(): self {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$pathInfo = rawurldecode($_SERVER['ORIG_PATH_INFO'] ?? $_SERVER['PATH_INFO'] ?? '/');

		return new Router($method, $pathInfo);
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	public function routeInNamespace(string $namespace, array $classArgs = []): self {
		if (($route = $this->findRouteInNamespace($namespace))) {
			[$class, $action, $args] = $route;
			$instance = new $class(...$classArgs);
			$this->handleRoute([$instance, $action], $args);
		}
		return $this;
	}

	/**
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	public function route(string $method, string $uri, $callable, array $classArgs = []): self {
		$uri = trim($uri, '/');

		if ($method == $this->method && preg_match("~^$uri$~", $this->uri, $matches)) {
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0](...$classArgs);
			$args = array_slice($matches, 1);
			$this->handleRoute($callable, $args);
		}

		return $this;
	}

	/**
	 * Defines a GET route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	public function get(string $uri, $callable, array $classArgs = []): self {
		return $this->route('GET', $uri, $callable, $classArgs);
	}

	/**
	 * Defines a POST route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
	 */
	public function post(string $uri, $callable, array $classArgs = []): self {
		return $this->route('POST', $uri, $callable, $classArgs);
	}

	/**
	 * Defines a PUT route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
	 */
	public function put(string $uri, $callable, array $classArgs = []): self {
		return $this->route('PUT', $uri, $callable, $classArgs);
	}

	/**
	 * Defines a DELETE route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
	 */
	public function delete(string $uri, $callable, array $classArgs = []): self {
		return $this->route('DELETE', $uri, $callable, $classArgs);
	}

	/**
	 * Defines a OPTIONS route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
	 */
	public function options(string $uri, $callable, array $classArgs = []): self {
		return $this->route('OPTIONS', $uri, $callable, $classArgs);
	}

	/**
	 * Defines a GET route, using route() method, that only outputs a template/view
	 * @param string $uri The URI for the route
	 * @param string $filename Template name
	 * @param array $data Optional data for the template
	 * @return Router
	 */
	public function view(string $uri, string $filename, array $data = []): self {
		return $this->route('GET', $uri, function () use ($filename, $data) {
			new class ($filename, $data) extends Controller {
				function __construct($filename, $data) {
					$this->view($filename, $data);
				}
			};
		});
	}

	/**
	 * Tries to find a route within a namespace
	 * @param string $namespace
	 * @return array|null
	 */
	public function findRouteInNamespace(string $namespace): ?array {
		$argv = ($this->uri !== '' ? explode('/', $this->uri) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = $this->classCase($argv[$i++]));
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = $this->findRouteInClass($found, $this->method, array_slice($argv, $i))))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = $this->findRouteInClass($found, $this->method, array_slice($argv, $i))))
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
				if ($m->getNumberOfRequiredParameters() <= count($route[2]) && $m->getNumberOfParameters() >= count($route[2]))
					return $route;
			}
			catch (Throwable $ignored) {}

		return null;
	}

	/**
	 * Dispatches the request to a callable
	 * @param callable $callable
	 * @param array $args
	 */
	private function handleRoute(callable $callable, array $args): void {
		$result = call_user_func_array($callable, $args);

		if (is_string($result))
			echo $result;
		elseif (is_array($result) || is_object($result)) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result);
		}

		if ($result !== false)
			exit;
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
