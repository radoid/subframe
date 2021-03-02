<?php
namespace Subframe;

/**
 * Implements the routing and MVC controller mechanisms
 * @package Subframe PHP Framework
 */
class Controller {

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param string[]|null $argv Optional arguments list; taken from the actual request if absent
	 * @param Cache $cache Optional cache if caching is desired
	 */
	public static function routeInNamespace($namespace = '', array $classArgs = [], $argv = null, $cache = null) {
		$argv = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1));
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

		if (($route = self::findRouteInNamespace($namespace, $method, $argv))) {
			[$class, $action, $args] = $route;
			$instance = new $class(...$classArgs);
			self::dispatch([$instance, $action], $args, $cache);
		}
	}

	/**
	 * Tries to dispatch the request within a directory
	 * @param string $directory The directory; current directory if not provided
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param string[]|null $argv Optional arguments list; taken from the actual request if absent
	 * @param Cache $cache Optional cache if caching is desired
	 */
	public static function routeInDirectory($directory = '.', array $classArgs = [], $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1)) ?: ['home'];
		for ($i = 0; $i < count($args); $i++) {
			$classname = self::classCase($args[$i]);
			$classpath = rtrim($directory.'/'.implode('/', array_slice($args, 0, $i)), '/');
			if (file_exists("$classpath/$classname.php")
					|| (($classname = $classname.'Controller') && file_exists("$classpath/$classname.php"))) {
				include_once "$classpath/$classname.php";
				self::routeInClass($classname, $classArgs, array_slice($args, $i + 1), $cache);
			}
		}
	}

	/**
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route
	 * @param callable $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param Cache $cache Optional cache if caching is desired
	 */
	public static function route($method, $uri, $callable, array $classArgs = [], $cache = null) {
		if ($method == $_SERVER['REQUEST_METHOD'] && preg_match("~^$uri/*$~", $_SERVER['REQUEST_URI'], $matches)) {
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0](...$classArgs);
			$args = array_slice($matches, 1);
			self::dispatch($callable, $args, $cache);
		}
	}

	/**
	 * Tries to find a route within a namespace
	 * @param string $namespace
	 * @param string $method HTTP method
	 * @param null $argv
	 * @return array|null
	 */
	public static function findRouteInNamespace($namespace, $method, $argv = null) {
		$argv = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1));
		$class = $namespace;
		for ($i = 0; $i <= count($argv); $i++) {
			if (class_exists($found = $class.'\\Home')
					|| class_exists($found = $class.'Controller')) {
				$args = array_slice($argv, $i);
				if (($action = self::findRouteInClass($found, $method, $args)))
					return [$found, $action, $args];
			}
			if ($i < count($argv)) {
				$class .= '\\'.self::classCase($argv[$i]);
				if (class_exists($found = $class)
						|| class_exists($found = $class.'Controller')) {
					$args = array_slice($argv, $i + 1);
					if (($action = self::findRouteInClass($found, $method, $args)))
						return [$found, $action, $args];
				}
			}
		}

		return null;
	}

	/**
	 * Tries to find an action within a specific class that fits in with the request's arguments
	 * @param string $classname The class in question
	 * @param string $method HTTP method
	 * @param string[] $args The request's arguments
	 * @return string|null The action (function) name
	 */
	public static function findRouteInClass($classname, $method, &$args) {
		$method = strtolower($method);
		// action || methodAction
		if (method_exists($classname, $fn = self::actionCase($method, @$args[0] ?: 'index'))
				|| method_exists($classname, $fn = self::actionCase('', @$args[0] ?: 'index'))) {
			array_splice($args, 0, 1);
			return $fn;
		// resource/action || resource/methodAction
		} else if (count($args) >= 2
				&& (method_exists($classname, $fn = self::actionCase($method, $args[1]))
						|| method_exists($classname, $fn = self::actionCase('', $args[1])))) {
			array_splice($args, 1, 1);
			return $fn;
		// resource
		} else if (count($args) >= 1 && method_exists($classname, $fn = $method)) {
			return $fn;
		} else
			return null;
	}

	/**
	 * Dispatches the request to a callable
	 * @param callable $callable
	 * @param array $args
	 * @param null $cache
	 */
	private static function dispatch(callable $callable, array $args, $cache = null) {
		$gzip = (strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") !== false && extension_loaded('zlib') ? ".gz" : '');

		$cachename = ($cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output".str_replace("/", "-", $_SERVER['REQUEST_URI']).".html$gzip" : false);
		if ($cachename) {
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

		if ($cachename)
			$cache->put($cachename, $gzip ? gzencode(ob_get_contents()) : ob_get_contents());

		if ($result !== false)
			exit;
	}

	/**
	 * Changes case of the argument as if it was a class name (capital letter on each word boundary)
	 * @param string $arg The argument
	 * @return string The result
	 */
	private static function classCase($arg) {
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
	private static function actionCase($method, $arg) {
		if (strpbrk($arg, '-.'))
			return strtr(lcfirst($method.ucwords($arg, '-.')), ['-' => '', '.' => '']);
		return ($method ? $method.ucfirst($arg) : $arg);
	}

	/**
	 * Obtains the current request's argument vector
	 * @return array The array with the arguments
	 */
	private static function argv() {
		global $argv;
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		$uri = substr($uri, 0, strcspn($uri, '?'));
		return ($uri ? explode('/', rtrim($uri, '/')) : ($argv ?? []));
	}

	/**
	 * Sets the ETag header and triggers 304 response if ETags match
	 * @param string $etag The ETag value
	 */
	protected function setETag($etag) {
		if (($before = $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
			if ($before == $etag) {
				http_response_code(304); // 304 Not Modified
				exit;
			}
		header("ETag: $etag");
	}

	/**
	 * Outputs a view/template provided with given data
	 * @param string $__filename The filename of the view, without ".php" extension
	 * @param array|object $__data The data
	 * @param int $__status The optional HTTP status code
	 * @throws \Exception
	 */
	protected function view($__filename, $__data = [], $__status = 200) {
		http_response_code($__status);
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE);
		extract((array) $__data);
		(include "$__filename.php")
			or $this->throw("\"$__filename\" view not found", 500);
		error_reporting($error_reporting);
	}

	/**
	 * Processes a view/template, provided with data, and returns its output
	 * @param string $filename The filename of the view, without ".php" extension
	 * @param array|object $data The data
	 * @return string The output
	 * @throws \Exception
	 */
	protected function getView($filename, $data = []) {
		ob_start();
		$this->view($filename, $data);
		return ob_get_clean();
	}

	/**
	 * Outputs JSON encoded object or array
	 * @param object|array $data The data to output
	 * @param int $status Optional HTTP status code
	 */
	protected function json($data = [], $status = 200) {
		http_response_code($status);
		@header("Content-Type: application/json");
		die(json_encode($data));
	}

	/**
	 * Throws an exception; for convenience, as it can be part of an expression
	 * @param string $message The message of the exception
	 * @param int $code The code of the exception
	 * @throws \Exception
	 */
	protected function throw($message, $code = 500) {
		throw new \Exception($message, $code);
	}

	/**
	 * Redirects the request to another URL
	 * @param string $url The URL to go to
	 * @param int $code HTTP status code, such as 301; defaults to 302
	 */
	protected function redirect($url, $code = 302) {
		http_response_code($code);
		header("Location: " . str_replace("\n", "\0", $url));
		exit;
	}

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public static function isAjax() {
		return (@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
	}

	/**
	 * Remote address the request was made from
	 * @return string
	 */
	public static function remoteAddr() {
		if (!empty ($_SERVER['HTTP_X_FORWARDED_FOR']))
			foreach (explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $_SERVER['REMOTE_ADDR'];
	}

}
