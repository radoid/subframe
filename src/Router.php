<?php
namespace Subframe;

/**
 * Implements the application routing
 * @package Subframe PHP Framework
 */
class Router {

	/** @var Request */
	private $request;

	/** @var string */
	private $method;

	/** @var string */
	private $uri;

	/** @var Cache|null */
	private $cache;


	/**
	 * Creates a router for the given request parameters or for the current request
	 * @param string|null $method HTTP method/verb
	 * @param string|null $uri requested URI; if not specified, it's resolved automatically — from REQUEST_URI, but relative to the script's directory (to also take care of small websites that reside in a subdirectory)
	 * @param Cache|null $cache
	 */
	public function __construct(?Request $request = null, ?Cache $cache = null) {
		$this->request = $request ?? Request::fromRelativeRequestUri();
		$this->method = $this->request->getMethod();
		$this->uri = trim(strtok($this->request->getUri(), '?'), '/');
		$this->cache = $cache;
	}

	/**
	 * Creates a router for the request from the REQUEST_URI constant
	 * @param Cache|null $cache
	 * @return Router
	 */
	public static function fromRequestUri(?Cache $cache = null): self {
		return new self(Request::fromRequestUri(), $cache);
	}

	/**
	 * Creates a router for the request from the PATH_INFO constant
	 * @param Cache|null $cache
	 * @return Router
	 */
	public static function fromPathInfo(?Cache $cache = null): self {
		return new self(Request::fromPathInfo(), $cache);
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param Cache|null $cache Optional cache if caching is desired
	 */
	public function routeInNamespace(string $namespace, array $classArgs = []) {
		if (($route = $this->findRouteInNamespace($namespace))) {
			[$class, $action, $args] = $route;
			$instance = new $class($this->request, ...$classArgs);
			self::executeCallable([$instance, $action], $args, $this->cache);
		}
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param Cache|null $cache Optional cache if caching is desired
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
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
	 */
	public function route(string $method, string $uri, $callable, array $classArgs = []): self {
		$uri = trim($uri, '/');

		if ($method == $this->method && preg_match("~^$uri$~", $this->uri, $matches)) {
			if (is_string($callable) && strpos($callable, '@') !== false)
				$callable = explode('@', $callable);
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0]($this->request, ...$classArgs);
			$args = array_slice($matches, 1);

			self::executeCallable($callable, $args, $cache ?? $this->cache);
		}

		return $this;
	}

	/**
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
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
	 * Defines a GET route, using route() method
	 * @param string $uri The URI for the route
	 * @param mixed $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @return Router
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
	 * Dispatches the request to a callable
	 * @param callable $callable
	 * @param array $args
	 * @param Cache|null $cache
	 */
	private static function executeCallable(callable $callable, array $args, Cache $cache = null) {
		$gzip = (strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', "gzip") !== false && extension_loaded('zlib') ? ".gz" : '');

		$cachename = ($cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output".str_replace("/", "-", $_SERVER['REQUEST_URI']).".html$gzip" : false);
		if ($cachename) {
			self::setETag(md5($cachename.$cache->getTime($cachename)));

			if (!empty ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				if ($cache->has($cachename, strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
					header_remove();
					http_response_code(304); // 304 Not Modified
					exit;
				}
			}
			header("Last-Modified: ".date("r"));
			header("Vary: Accept-Encoding");
			if ($cache->has($cachename)) {
				if ($gzip) {
					ini_set('zlib.output_compression', 'Off');
					header('Content-Encoding: gzip');
				}
				$cache->dump($cachename);
				exit;
			}
		}

		if ($cachename)
			ob_start();

		$result = call_user_func_array($callable, $args);

		if (is_string($result))
			echo $result;
		elseif (is_array($result) || is_object($result)) {
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result);
		}

		if ($cachename)
			$cache->put($cachename, $gzip ? gzencode(ob_get_contents()) : ob_get_contents());

		if ($result !== false)
			exit;
	}

	private static function captureCallable(callable $callable, array $args): Response {
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
		else
			$response = new Response($status, $headers, $output);

		return $response;
	}

	/**
	 * Changes case of the argument as if it was a class name (capital letter on each word boundary)
	 * @param string $arg The argument
	 * @return string The result
	 */
	private static function classCase(string $arg): string {
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
	private static function actionCase(string $method, string $arg): string {
		if (strpbrk($arg, '-.'))
			return strtr(lcfirst($method.ucwords($arg, '-.')), ['-' => '', '.' => '']);
		return ($method ? $method.ucfirst($arg) : $arg);
	}

	/**
	 * Sets the ETag header and triggers 304 response if ETags match
	 * @param string $etag The ETag value
	 */
	protected static function setETag(string $etag) {
		if (($before = $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
			if ($before == $etag) {
				http_response_code(304); // 304 Not Modified
				exit;
			}
		header("ETag: $etag");
	}

}
