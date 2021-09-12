<?php
namespace Subframe;

use ReflectionMethod;

/**
 * Implements the application routing
 * @package Subframe PHP Framework
 */
class Router {

	/**
	 * All defined routes
	 */
	private array $routes = [];


	/**
	 * The constructor
	 * @param string|null $namespace The optional namespace, its classes and methods representing routes
	 */
	public function __construct(?string $namespace = null) {
		if ($namespace)
			$this->addNamespace($namespace);
	}

	/**
	 * Adds a route defined with a regular expression and a callable
	 * @param string $method The route's method
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param callable|array $callable The callable to be executed
	 * @param array $classArgs In case a class needs to be instantiated for the callable, the arguments for its constructor
	 * @return self The object itself
	 */
	public function addRoute(string $method, string $path, $callable, array $classArgs = []): self {
		$this->routes[] = [$method, $path, $callable, $classArgs];

		return $this;
	}

	/**
	 * Adds a view route, only presenting the given view
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param string $filename The view's filename
	 * @param array $data Optional data for the view
	 * @return self The object itself
	 */
	public function addView(string $path, string $filename, array $data = []): self {
		$this->routes[] = [null, $path, $filename, $data];

		return $this;
	}

	/**
	 * Adds a namespace with its classes and methods as routes
	 * @param string $namespace The namespace
	 * @param array $classArgs Optional arguments for the class constructor
	 * @return self The object itself
	 */
	public function addNamespace(string $namespace, array $classArgs = []): self {
		$this->routes[] = [null, null, $namespace, $classArgs];

		return $this;
	}

	/**
	 * Tries to dispatch the given request among its routes
	 * @param Request $request The incoming request
	 * @return ?Response The response if a route was found and executed, otherwise null
	 */
	public function dispatch(Request $request): ?Response {
		Container::set(Request::class, $request);
		
		foreach ($this->routes as [$method, $path, $action, $data]) {
			if ($method)
				$route = $this->matchRoute($request, $method, $path, $action, $data);
			elseif ($path !== null)
				$route = $this->matchView($request, $path, $action, $data);
			else
				$route = $this->matchNamespace($request, $action, $data);
			
			if ($route) {
				$response = $this->captureResponse($route);
				return $response;
			}
		}

		return null;
	}

	/**
	 * Tries to match a view-route to the request
	 * @param Request $request The request
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param string $filename The view's filename
	 * @param array $data Optional data for the view
	 * @return ?array The "route" containing a callable that returns the view as a Response
	 */
	public function matchView(Request $request, string $path, string $filename, array $data = []): ?array {
		if ($request->getMethod() == 'GET' && $path == $request->getPath())
			return [fn() => Response::fromView($filename, $data)];

		return null;
	}

	/**
	 * Tries to match a regular expression route to the request
	 * @param Request $request The request
	 * @param string $method The route's method
	 * @param string $path The route's path, without trailing slash or query parameters
	 * @param callable|string $callable A closure or [Controller, action] combination
	 * @param array $classArgs In case a class needs to be instantiated for the callable, the arguments for its constructor
	 * @return ?array The "route" containing a callable and its arguments
	 */
	public function matchRoute(Request $request, string $method, string $path, $callable, array $classArgs = []): ?array {
		if ($method == $request->getMethod() && preg_match("~^$path$~", $request->getPath(), $matches))
			return [$callable, array_slice($matches, 1), $classArgs];

		return null;
	}

	/**
	 * Tries to match the given namespace of routes to the request
	 * @param Request $request The request
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments for the instantiated class constructor
	 * @return ?array An array [callable, args] representing the route
	 */
	public function matchNamespace(Request $request, string $namespace, array $classArgs = []): ?array {
		$method = $request->getMethod();
		$path = trim($request->getPath(), '/');
		$argv = ($path !== '' ? explode('/', $path) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = $this->classCase($argv[$i++])) {}
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = $this->matchClass($found, $method, array_slice($argv, $i), $classArgs)))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = $this->matchClass($found, $method, array_slice($argv, $i), $classArgs)))
						return $route;
		}

		return null;
	}

	/**
	 * Tries to find a route within a class, matching a method name and its arguments
	 * @param string $class The class to find a route in
	 * @param string $requestMethod The request's method
	 * @param string[] $args The parts of the request's path
	 * @param array $classArgs Optional arguments for the class' constructor
	 * @return ?array An array [callable, args] representing the route
	 */
	public function matchClass(string $class, string $requestMethod, array $args, array $classArgs = []): ?array {
		$requestMethod = strtolower($requestMethod);
		$count = count($args);

		// index or methodIndex
		if ($count == 0
			&& (method_exists($class, $fn = "{$requestMethod}Index")
					|| method_exists($class, $fn = 'index')))
			$route = [[$class, $fn], [], $classArgs];

		// action or methodAction
		else if ($count > 0
			&& (method_exists($class, $fn = $this->actionCase($requestMethod, $args[0]))
					|| method_exists($class, $fn = $this->actionCase('', $args[0]))))
			$route = [[$class, $fn], array_slice($args, 1), $classArgs];

		// resource+action or resource+methodAction
		else if ($count >= 2
				&& (method_exists($class, $fn = $this->actionCase($requestMethod, $args[1]))
						|| method_exists($class, $fn = $this->actionCase('', $args[1])))) {
			array_splice($args, 1, 1);
			$route = [[$class, $fn], $args, $classArgs];

		// resource
		} else if (method_exists($class, $fn = $requestMethod))
			$route = [[$class, $fn], $args, $classArgs];

		if (isset($route)
				&& (new ReflectionMethod($route[0][0], $route[0][1]))->isPublic()
				&& Container::canInjectParameters($route[0], $route[1]))
			return $route;

		return null;
	}

	/**
	 * Makes a call to the controller and encapsulates the response into a Response object if it isn't one
	 * @param mixed $route The "route" representing the controller
	 * @return ?Response The Response object
	 */
	private function captureResponse(array $route): Response {
		$response = Container::call($route[0], $route[1] ?? [], $route[2] ?? []);

		if (!($response instanceof Response)) {
			$status = http_response_code();
			
			if (is_string($response))
				$response = new Response($response, $status, []);
			elseif (is_array($response) || is_object($response))
				$response = Response::fromJson($response, $status);
			else
				$response = new Response('', $status, []);
		}

		return $response;
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
