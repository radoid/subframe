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
	public function addRoute(string $method, string $uri, $action): self {
		$this->routes[] = [$method, $uri, $action];

		return $this;
	}

	/**
	 * Adds a view route, only presenting the given view
	 */
	public function addView(string $uri, string $filename, array $data = []): self {
		$this->routes[] = [null, $uri, $filename, $data];

		return $this;
	}

	/**
	 * Adds a namespace with its classes as routes
	 */
	public function addNamespace(string $namespace): self {
		$this->routes[] = [null, null, $namespace];

		return $this;
	}

	/**
	 * Tries to dispatch the given request among defined routes, returns one's Response if found
	 */
	public function handle(Request $request): ?Response {
		Container::set(Request::class, $request);
		
		foreach ($this->routes as [$method, $uri, $action]) {
			if ($method)
				$response = $this->tryRoute($request, $method, $uri, $action);
			elseif ($uri !== null)
				$response = $this->tryViewRoute($request, $uri, $action);
			else
				$response = $this->tryNamespace($request, $action);
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
	private function tryNamespace(Request $request, string $namespace): ?Response {
		if (($route = $this->findRouteInNamespace($request, $namespace))) {
			[$class, $action, $args] = $route;
			$instance = Container::get($class);
			$response = $this->captureResponse([$instance, $action], $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the given request, and returns the provided callable's response if matched
	 * @param Request $request
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route, without trailing slash or query parameters
	 * @param callable|string $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?Response
	 */
	private function tryRoute(Request $request, string $method, string $uri, $callable): ?Response {
		$requestUri = '/' . trim(strtok($request->getUri(), '?'), '/');
		
		if ($method == $request->getMethod() && preg_match("~^$uri$~", $requestUri, $matches)) {
			$args = array_slice($matches, 1);
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				if (self::findArguments($callable[0], $callable[1], $args))
					$callable[0] = Container::get($callable[0]);

			$response = $this->captureResponse($callable, $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the request, and returns its response if matched
	 */
	private function tryViewRoute(Request $request, string $uri, string $filename, array $data = []): ?Response {
		$uri = '/' . trim($uri, '/');
		$requestUri = '/' . trim(strtok($request->getUri(), '?'), '/');

		if ($request->getMethod() == 'GET' && $uri == $requestUri)
			$response = Response::fromView($filename, $data);
		else
			$response = null;

		return $response;
	}

	/**
	 * Executes given callable and returns a Response made from its output
	 */
	private function captureResponse(callable $callable, array $args): ?Response {
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

		if (isset($route) && $this->findArguments($route[0], $route[1], $route[2]))
			return $route;
		else
			return null;
	}

	private function findArguments(string $class, string $fn, array &$args): bool {
		// try {
		$r = new ReflectionClass($class);
		$m = $r->getMethod($fn);
		$paramCount = $requiredCount = 0;
		foreach ($m->getParameters() as $p)
			if (!$p->getType() || $p->getType()->isBuiltin()) {
				$paramCount += 1;
				$requiredCount += ($p->isOptional() ? 0 : 1);
			}
		if (count($args) >= $requiredCount && count($args) <= $paramCount) {
			foreach ($m->getParameters() as $i => $p)
				if ($p->getType() && !$p->getType()->isBuiltin()) {
					$object = Container::get($p->getType()->getName());
					array_splice($args, $i, 0, [$object]);
				}
			return true;
		}
		// catch (Throwable $ignored) {}

		return false;
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
