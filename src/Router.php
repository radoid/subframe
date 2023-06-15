<?php
namespace Subframe;

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
	public function addRoute(string $method, string $uri, $action, array $classArgs = []): void {
		$this->routes[] = [$method, $uri, $action, $classArgs];
	}

	/**
	 * Adds a view route, only presenting the given view
	 */
	public function addView(string $uri, string $filename, array $data = []): void {
		$this->routes[] = [null, $uri, $filename, $data];
	}

	/**
	 * Adds a namespace with its classes as routes
	 */
	public function addNamespace(string $namespace, array $classArgs = []): void {
		$this->routes[] = [null, null, $namespace, $classArgs];
	}

	/**
	 * Tries to dispatch the given request among defined routes, returns one's Response if found
	 */
	public function handle(Request $request): ?Response {
		foreach ($this->routes as [$method, $uri, $action, $classArgs]) {
			if ($method)
				$response = $this->captureRoute($request, $method, $uri, $action, $classArgs);
			elseif ($uri !== null)
				$response = $this->captureViewRoute($request, $uri, $action, $classArgs);
			else
				$response = $this->captureRouteInNamespace($request, $action, $classArgs);
			if ($response)
				return $response;
		}

		return null;
	}

	/**
	 * Tries to dispatch the given request within a namespace, returns route's Response if found
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	private function captureRouteInNamespace(Request $request, string $namespace, array $classArgs = []): ?Response {
		if (($route = $this->findRouteInNamespace($request, $namespace))) {
			[$class, $action, $args] = $route;
			$instance = new $class($request, ...$classArgs);
			$response = self::captureCallable([$instance, $action], $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the given request, and returns the provided callable's response if matched
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route, without trailing slash or query parameters
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?Response
	 */
	private function captureRoute(Request $request, string $method, string $uri, $callable, array $classArgs = []): ?Response {
		if ($method == $request->getMethod() && preg_match("~^$uri$~", $request->getUri(), $matches)) {
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0]($request, ...$classArgs);
			$args = array_slice($matches, 1);

			$response = self::captureCallable($callable, $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the request, and returns its response if matched
	 */
	private function captureViewRoute(Request $request, string $uri, string $filename, array $data = []): ?Response {
		$uri = trim($uri, '/');

		if ($request->getMethod() == 'GET' && $uri == $request->getUri())
			$response = Response::fromView($filename, $data);
		else
			$response = null;

		return $response;
	}

	/**
	 * Executes given callable and returns a Response made from its output
	 */
	private static function captureCallable(callable $callable, array $args): ?Response {
		$result = call_user_func_array($callable, $args);
		$status = http_response_code();

		if ($result instanceof Response)
			$response = $result;
		elseif (is_string($result))
			$response = new Response($result, $status, []);
		elseif (is_array($result) || is_object($result))
			$response = new Response(json_encode($result), $status, ['Content-Type' => 'application/json; charset=utf-8']);
		elseif ($result !== false)
			$response = new Response('', $status, []);
		else
			$response = null;

		return $response;
	}

	/**
	 * Tries to find a route within a namespace
	 */
	public function findRouteInNamespace(Request $request, string $namespace): ?array {
		$method = $request->getMethod();
		$uri = trim($request->getUri(), '/');
		$argv = ($uri !== '' ? explode('/', $uri) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = self::classCase($argv[$i++]));
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = self::findRouteInClass($found, $method, array_slice($argv, $i))))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = self::findRouteInClass($found, $method, array_slice($argv, $i))))
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
	public static function findRouteInClass(string $classname, string $method, array $args): ?array {
		$method = strtolower($method);
		$count = count($args);

		// index or methodIndex
		if ($count == 0
			&& (method_exists($classname, $fn = "{$method}Index")
					|| method_exists($classname, $fn = 'index')))
			$route = [$classname, $fn, []];

		// action or methodAction
		else if ($count > 0
			&& (method_exists($classname, $fn = self::actionCase($method, $args[0]))
					|| method_exists($classname, $fn = self::actionCase('', $args[0]))))
			$route = [$classname, $fn, array_slice($args, 1)];

		// resource+action or resource+methodAction
		else if ($count >= 2
				&& (method_exists($classname, $fn = self::actionCase($method, $args[1]))
						|| method_exists($classname, $fn = self::actionCase('', $args[1])))) {
			array_splice($args, 1, 1);
			$route = [$classname, $fn, $args];

		// resource
		} else if (method_exists($classname, $fn = $method))
			$route = [$classname, $fn, $args];

		if (isset($route))
			try {
				$r = new \ReflectionClass($classname);
				$m = $r->getMethod($route[1]);
				if ($m->isPublic() && $m->getNumberOfRequiredParameters() <= count($route[2]) && $m->getNumberOfParameters() >= count($route[2]))
					return $route;
			}
			catch (\Throwable $ignored) {}

		return null;
	}

	/**
	 * Changes case of the argument as if it was a class name (capital letter on each word boundary)
	 * @param string $arg The argument
	 * @return string The result
	 */
	protected static function classCase(string $arg): string {
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
	protected static function actionCase(string $method, string $arg): string {
		if (strpbrk($arg, '-.'))
			return strtr(lcfirst($method.ucwords($arg, '-.')), ['-' => '', '.' => '']);
		return ($method ? $method.ucfirst($arg) : $arg);
	}

}
