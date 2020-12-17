<?php
namespace Subframe;

/**
 * Implements the MVC controller mechanisms
 * @package Subframe PHP Framework
 */
class Controller {

	/** @var Cache */
	protected static $cache;

	/**
	 * Tries to dispatch the request within a class
	 * @param string $class The class name
	 * @param string[]|null $argv Optional arguments list; taken from the actual request if absent
	 * @param Cache $cache Optional cache if caching is desired
	 */
	static public function dispatchInClass($class, $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1));
		if ($cache)
			self::$cache = $cache;

		if (!($action = self::findActionInClass($class, $args)))
			return;

		$gzip = (strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") !== false && extension_loaded('zlib') ? ".gz" : '');

		$cachename = (self::$cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output" . str_replace("/", "-", self::pathInfo()) . ".html$gzip" : false);
		if ($cachename) {
			if (!empty ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				if (self::$cache->ready($cachename, strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
					header_remove();
					http_response_code(304); // 304 Not Modified
					exit;
				}
			}
			header("Last-Modified: " . date("r"));
			header("Vary: Accept-Encoding");
			if (self::$cache->ready($cachename)) {
				if ($gzip) {
					ini_set('zlib.output_compression', 'Off');
					header('Content-Encoding: gzip');
				}
				self::$cache->dump($cachename);
				exit;
			}
		}

		if ($cachename)
			ob_start();

		$instance = new $class;
		$result = call_user_func_array([$instance, $action], $args);

		if ($cachename)
			self::$cache->put($cachename, $gzip ? gzencode(ob_get_contents()) : ob_get_contents());

		if ($result !== false)
			exit;
	}

	/**
	 * Tries to dispatch the request within a namespace
	 * @param string $namespace The namespace; the root namespace if empty
	 * @param string[]|null $argv Optional arguments list; taken from the actual request if absent
	 * @param Cache $cache Optional cache if caching is desired
	 */
	static public function dispatchInNamespace($namespace = '', $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1)) ?: ['home'];
		for ($i = 1; $i <= count($args); $i++) {
			$classname = $namespace.'\\'.implode('\\', array_map([self::class, 'classCase'], array_slice($args, 0, $i)));
			if (class_exists($classname)
					|| (($classname = $classname.'Controller') && class_exists($classname)))
				self::dispatchInClass($classname, array_slice($args, $i), $cache);
		}
	}

	/**
	 * Tries to dispatch the request within a directory
	 * @param string $directory The directory; current directory if not provided
	 * @param string[]|null $argv Optional arguments list; taken from the actual request if absent
	 * @param Cache $cache Optional cache if caching is desired
	 */
	static public function dispatchInDirectory($directory = '.', $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1)) ?: ['home'];
		for ($i = 0; $i < count($args); $i++) {
			$classname = self::classCase($args[$i]);
			$classpath = rtrim($directory.'/'.implode('/', array_slice($args, 0, $i)), '/');
			if (file_exists("$classpath/$classname.php")
					|| (($classname = $classname.'Controller') && file_exists("$classpath/$classname.php"))) {
				include_once "$classpath/$classname.php";
				self::dispatchInClass($classname, array_slice($args, $i+1), $cache);
			}
		}
	}

	/**
	 * Defines a route: checks if the request is compatible with the given URI, and routes the request to the given callable if it is
	 * @param string $method The HTTP request method
	 * @param string $uri The URI for the route
	 * @param callable $callable A closure or [Controller, action] combination
	 */
	static public function route($method, $uri, $callable) {
		if ($method == $_SERVER['REQUEST_METHOD'] && preg_match("~^$uri/*$~", $_SERVER['REQUEST_URI'], $matches)) {
			[$className, $classMethod] = $callable;
			$instance = new $className;
			$args = array_slice($matches, 1);
			if (call_user_func_array([$instance, $classMethod], $args) !== false)
				exit;
		}
	}

	/**
	 * Tries to find an action within a specific class that fits in with the request's arguments
	 * @param string $classname The class in question
	 * @param string[] $args The request's arguments
	 * @return string|null The action (function) name
	 */
	private static function findActionInClass($classname, &$args) {
		$method = strtolower(@$_SERVER['REQUEST_METHOD']);
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
		return $method.ucfirst($arg);
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
			or $this->throw("View $__filename was not found.", 500);
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
	 * Obtains the PATH_INFO system variable
	 * @return string The variable
	 */
	protected static function pathInfo() {
		return str_replace($_SERVER['SCRIPT_NAME'], '', $_SERVER['PATH_INFO'] ?? ($_SERVER['ORIG_PATH_INFO'] ?? ''));
	}

	/**
	 * Obtains the current script's arguments
	 * @return array The array with the arguments
	 */
	protected static function argv() {
		global $argv;
		$pathInfo = self::pathInfo();
		return ($pathInfo ? explode('/', rtrim($pathInfo, '/')) : ($argv ?? []));
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
	static public function isAjax() {
		return (@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
	}

	/**
	 * Remote address the request was made from
	 * @return string
	 */
	static public function remoteAddr() {
		if (!empty ($_SERVER['HTTP_X_FORWARDED_FOR']))
			foreach (explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $_SERVER['REMOTE_ADDR'];
	}

}
