<?php
namespace Subframe;

/**
 * Implements the application routing
 * @package Subframe PHP Framework
 */
class Router {

	/**
	 * The handled request
	 * @var Request
	 */
	protected $request;

	/**
	 * The method of the handled request
	 * @var string
	 */
	protected $method;

	/**
	 * The URI of the handled request
	 * @var string
	 */
	protected $uri;


	/**
	 * Creates a router for the given request parameters or for the current request
	 * @param Request|null $request
	 */
	public function __construct(?Request $request = null) {
		$this->request = $request ?? Request::fromRelativeRequestUri();
		$this->method = $this->request->getMethod();
		$this->uri = trim(strtok($this->request->getUri(), '?'), '/');
	}

	/**
	 * Creates a router for the request from the REQUEST_URI constant
	 * @return Router
	 */
	public static function fromRequestUri(): self {
		return new self(Request::fromRequestUri());
	}

	/**
	 * Creates a router for the request from the PATH_INFO constant
	 * @return Router
	 */
	public static function fromPathInfo(): self {
		return new self(Request::fromPathInfo());
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 */
	public function captureRouteInNamespace(string $namespace, array $classArgs = []): ?Response {
		if (($route = $this->findRouteInNamespace($namespace))) {
			[$class, $action, $args] = $route;
			$instance = new $class($this->request, ...$classArgs);
			$response = self::captureCallable([$instance, $action], $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the request, and returns given callable's response if successful
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return ?Response
	 */
	public function captureRoute(string $method, string $uri, $callable, array $classArgs = []): ?Response {
		$uri = trim($uri, '/');

		if ($method == $this->method && preg_match("~^$uri$~", $this->uri, $matches)) {
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0]($this->request, ...$classArgs);
			$args = array_slice($matches, 1);

			$response = self::captureCallable($callable, $args);
		} else
			$response = null;

		return $response;
	}

	/**
	 * Tries to match a route to the request, and returns a view as a response if successful
	 * @return ?Response
	 */
	public function captureViewRoute(string $uri, string $filename, array $data = []): ?Response {
		$uri = trim($uri, '/');

		if ($this->method == 'GET' && $uri == $this->uri)
			$response = Response::fromView($filename, $data);
		else
			$response = null;

		return $response;
	}

	/**
	 * Executes given callable and returns a Response made from its output
	 */
	protected static function captureCallable(callable $callable, array $args): ?Response {
		ob_start();
		$result = call_user_func_array($callable, $args);
		$output = ob_get_clean();
		$headers = headers_list();
		$status = http_response_code();

		if ($result instanceof Response)
			$response = $result;
		elseif (is_string($result))
			$response = new Response($status, $headers, $result);
		elseif (is_array($result) || is_object($result))
			$response = new Response($status, array_merge($headers, ['Content-Type: application/json; charset=utf-8']), json_encode($result));
		elseif ($result !== false)
			$response = new Response($status, $headers, $output);
		else
			$response = null;

		return $response;
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
		for ($i = 0; $i < $argc; $classv[] = self::classCase($argv[$i++]));
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\\Home'))
				if (($route = self::findRouteInClass($found, $this->method, array_slice($argv, $i))))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = self::findRouteInClass($found, $this->method, array_slice($argv, $i))))
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
				if ($m->getNumberOfRequiredParameters() <= count($route[2]) && $m->getNumberOfParameters() >= count($route[2]))
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
