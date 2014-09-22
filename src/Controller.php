<?php
namespace Subframe;

use Exception;
use ReflectionClass;

/**
 * Base MVC controller implementation
 * @package Subframe PHP Framework
 */
class Controller {

	/**
	 * Outputs a view/template provided with given data
	 * @param string $__filename The filename of the view, without ".php" extension
	 * @param array $__data The data
	 * @param int $__status The optional HTTP status code
	 * @throws Exception
	 */
	protected function view($__filename, array $__data = [], $__status = 200) {
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);

		http_response_code($__status);
		extract($__data);
		require "$__filename.php";

		error_reporting($error_reporting);
	}

	/**
	 * Outputs JSON encoded object or array
	 * @param array $data The data to output
	 * @param int $status Optional HTTP status code
	 */
	protected function json(array $data = [], $status = 200) {
		$json = json_encode((object)$data);
		http_response_code($status);
		header('Content-Type: application/json');
		header('Content-Length: '.strlen($json));
		echo $json;
	}

	/**
	 * Redirects the request to another URL
	 * @param string $url The URL to go to
	 * @param int $code HTTP status code, such as 301; defaults to 302
	 */
	protected function redirect($url, $code = 302) {
		http_response_code($code);
		header('Location: ' . str_replace("\n", "\0", $url));
		exit;
	}

	/**
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
	 * @param string $method The HTTP request method
	 * @param string $path The URI for the route
	 * @param callable $callable A closure or [Controller, action] combination
	 * @param array $classArgs Optional arguments to the callable's class (if any) constructor
	 * @param string $requestMethod Optional request method (if not $_SERVER['REQUEST_METHOD'])
	 * @param string $requestPath Optional request path (if not $_SERVER['REQUEST_URI'])
	 */
	public static function route($method, $path, $callable, array $classArgs = [], $requestMethod = '', $requestPath = '') {
		$requestMethod = ($requestMethod ?: $_SERVER['REQUEST_METHOD']);
		$requestPath = '/' . trim($requestPath !== '' ? $requestPath : self::getGlobalRequestUri(), '/');
		$path = '/' . trim($path, '/');

		if ($method == $requestMethod && preg_match("~^$path$~", $requestPath, $matches)) {
			if (is_array($callable) && is_string($callable[0]))
				$callable[0] = new $callable[0](...$classArgs);
			$args = array_slice($matches, 1);
			
			call_user_func_array($callable, $args);
			exit;
		}
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param array $classArgs Optional arguments to the found class' constructor
	 * @param string $requestMethod Optional request method (if not $_SERVER['REQUEST_METHOD'])
	 * @param string $requestPath Optional request path (if not $_SERVER['REQUEST_URI'])
	 */
	public static function routeInNamespace($namespace = '', array $classArgs = [], $requestMethod = '', $requestPath = '') {
		$requestMethod = $requestMethod ?: $_SERVER['REQUEST_METHOD'] ?: 'GET';
		$requestPath = '/' . trim($requestPath !== '' ? $requestPath : self::getGlobalRequestUri(), '/');

		if (($route = self::findRouteInNamespace($namespace, $requestMethod, $requestPath))) {
			list($class, $action, $args) = $route;
			$instance = new $class(...$classArgs);

			call_user_func_array([$instance, $action], $args);
			exit;
		}
	}

	/**
	 * Tries to find a route within a namespace
	 * @param string $namespace
	 * @param string $requestMethod The request's method
	 * @param string $requestPath The request's path
	 * @return array|null
	 */
	public static function findRouteInNamespace($namespace, $requestMethod, $requestPath) {
		$requestPath = trim($requestPath, '/');
		$argv = ($requestPath !== '' ? explode('/', $requestPath) : []);
		$argc = count($argv);

		$classv = [$namespace];
		for ($i = 0; $i < $argc; $classv[] = self::classCase($argv[$i++]));
		for ($i = $argc; $i >= 0; $i--) {
			$class = join('\\', array_slice($classv, 0, 1+$i));
			if (class_exists($found = $class.'\Home'))
				if (($route = self::findRouteInClass($found, $requestMethod, array_slice($argv, $i))))
					return $route;
			if ($i > 0)
				if (class_exists($found = $class))
					if (($route = self::findRouteInClass($found, $requestMethod, array_slice($argv, $i))))
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
	public static function findRouteInClass($classname, $method, $args) {
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

		if (isset($route)) {
			$r = new ReflectionClass($classname);
			$m = $r->getMethod($route[1]);
			if ($m->getNumberOfRequiredParameters() <= count($route[2]) && $m->getNumberOfParameters() >= count($route[2]))
				return $route;
		}

		return null;
	}

	/**
	 * Sets the ETag header and triggers the 304 response if it matches to the requested ETag
	 * @param string $etag The ETag value
	 */
	protected static function setETag($etag) {
		if (($before = $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
			if ($before == $etag) {
				header_remove();
				http_response_code(304); // 304 Not Modified
				exit;
			}
		
		header("ETag: $etag");
	}

	/**
	 * Sets the Last-Modified header and triggers the 304 response if timestamp not newer then requested
	 * @param int $timestamp The Unix timestamp
	 */
	protected static function setLastModified($timestamp) {
		if (($before = $_SERVER['HTTP_IF_MODIFIED_SINCE']))
			if (strtotime($before) >= $timestamp) {
				header_remove();
				http_response_code(304); // 304 Not Modified
				exit;
			}
		
		header('Last-Modified: '.date('r', $timestamp));
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
	 * Obtains the current request URI
	 * @return string
	 */
	private static function getGlobalRequestUri() {
		$path = (isset($_SERVER['REDIRECT_URL']) ? $_SERVER['REDIRECT_URL'] : rawurldecode(strtok($_SERVER['REQUEST_URI'], '?')));

		return $path;
	}

}
