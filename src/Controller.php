<?php
/**
 * Implements the controller mechanism in MVC paradigm
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Controller
{
	static $cache;

	static function dispatchInClass($class, $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1));
		if ($cache)
			self::$cache = $cache;

		if (!($action = self::findActionInClass($class, $args)))
			return;

		$gzip = (strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") !== false && extension_loaded('zlib') ? ".gz" : "");

		$cachename = (self::$cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output" . str_replace("/", "-", self::path_info()) . ".html$gzip" : false);
		if ($cachename) {
			if (!empty ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				if (self::$cache->ready($cachename, strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
					header_remove();
					header('HTTP/1.0 304 Not Modified');
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

	static function dispatchInNamespace($namespace = '', $argv = null, $cache = null) {
		$args = (is_string($argv) ? explode('/', trim($argv, '/')) : $argv ?? array_slice(self::argv(), 1)) ?: ['home'];
		for ($i = 1; $i <= count($args); $i++) {
			$classname = $namespace.'\\'.implode('\\', array_map([self::class, 'classCase'], array_slice($args, 0, $i)));
			if (class_exists($classname)
					|| (($classname = $classname.'Controller') && class_exists($classname)))
				self::dispatchInClass($classname, array_slice($args, $i), $cache);
		}
	}

	static function dispatchInDirectory($directory = '.', $argv = null, $cache = null) {
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

	static function route($method, $uri, $callable) {
		if ($method == $_SERVER['REQUEST_METHOD'] && preg_match("~^$uri/*$~", $_SERVER['REQUEST_URI'], $matches)) {
			list($className, $classMethod) = $callable;
			$instance = new $className;
			$args = array_slice($matches, 1);
			if (call_user_func_array([$instance, $classMethod], $args) !== false)
				exit;
		}
	}

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

	private static function classCase($name) {
		if (strpbrk($name, '-.'))
			//return preg_replace_callback('~-+(\w)~', function ($m) {return strtoupper($m[1]);}, ucfirst($action));
			return strtr(ucwords($name, '-.'), ['-' => '', '.' => '']);
		return ucfirst($name);
	}

	private static function actionCase($method, $name) {
		if (strpbrk($name, '-.'))
			//return preg_replace_callback('~-+(\w)~', function ($m) {return strtoupper($m[1]);}, $method.ucfirst($action));
			return strtr(lcfirst($method.ucwords($name, '-.')), ['-' => '', '.' => '']);
		return $method.ucfirst($name);
	}

	protected function view($_view, $_data = [], $_status = 200) {
		extract((array) $_data);
		if (is_string($_status))
			@header("HTTP/1.1 $_status");
		elseif (is_numeric($_status) && $_status != 200)
			@header("HTTP/1.1 $_status Unexpected Error");
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE);
		//$include_path = set_include_path(self::$views);
		(include "Controller.php")
			or trigger_error("View $_view was not found.", E_USER_ERROR);
		//set_include_path($include_path);
		error_reporting($error_reporting);
	}

	protected function ob_view($_view, $_data = []) {
		ob_start();
		$this->view($_view, $_data);
		return ob_get_clean();
	}

	protected function json($data = [], $status = 200) {
		if (is_string($status))
			@header("HTTP/1.1 $status");
		elseif ($status && $status != 200)
			@header("HTTP/1.1 $status Unexpected Error");
		if ($status)
			@header("Content-Type: application/json; charset=utf-8");
		echo json_encode($data);
	}

	protected function log($text, $extended = false, $filename = "debug.log") {
		$text = date("d/m/Y H:i:s") . "  " . (is_string($text) ? $text : json_encode($text));
		if ($extended)
			$text .= " ⋅ " . self::remote_addr() . " ⋅ " . self::user_agent();
		//	$text .= @" ⋅ ← $_SERVER[HTTP_REFERER]";
		file_put_contents($filename, "$text\n", FILE_APPEND);
	}

	protected function throw($message, $code = 500) {
		throw new \Exception($message, $code);
	}

	/**
	 * Fetches the PATH_INFO system variable
	 * @return string the variable
	 */
	static function path_info() {
		return str_replace($_SERVER['SCRIPT_NAME'], "", (!empty ($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (!empty ($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : "")));
	}

	/**
	 * Fetches current script arguments in MVC fashion
	 * @return array Array with the script name, controller name, action name, then arguments from path info, then GET arguments
	 */
	static function argv() {
		global $argv;
		$path_info = self::path_info();
		return ($path_info ? explode('/', rtrim($path_info, '/')) : (isset ($argv) ? $argv : []));
	}

	protected function redirect($url, $code = 302) {
		if ($code == 301)
			header("HTTP/1.1 301 Moved Permanently");
		header("Location: " . str_replace("\n", "\0", $url));
		exit;
	}

	static function isAjax() {
		return (@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
	}

	static function isJson() {
		return (strpos(@$_SERVER['HTTP_ACCEPT'], "/json") !== false);
	}

	static function remote_addr() {
		if (!empty ($_SERVER['HTTP_X_FORWARDED_FOR']))
			foreach (explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $_SERVER['REMOTE_ADDR'];
	}

	static function user_agent($agent = "") {
		$agent = $agent ? $agent : $_SERVER['HTTP_USER_AGENT'];
		if (preg_match('~\b(?:iPad|iPhone|iPod); U?;? ?CPU (?:OS|iPhone OS) ([0-9]+)~', $agent, $m))
			$os = str_replace('_', '.', "iOS $m[1]");
		elseif (preg_match('~\b(Mac OS X [0-9_.]+|Windows NT [0-9.]+|Windows 9\d+|Android\b ?[0-9.]*|Linux \w+|Windows Phone O?S? ?[0-9.]+\b)~', $agent, $m))
			$os = strtr($m[1], ['Mac OS X' => 'macOS', 'Windows NT 5.1' => 'Windows XP', 'Windows NT 6.0' => 'Windows Vista', 'Windows NT 6.1' => 'Windows 7', 'Windows NT 6.2' => 'Windows 8', 'Windows NT 6.3' => 'Windows 8.1']);
		if (!empty ($os))
			if (preg_match('~\b(Opera/|Firefox/|Chrome/|CriOS/|Safari/|MSIE |Trident/)([0-9]+)~', $agent, $m))
				$agent = str_replace(['CriOS', 'Trident 7'], ['Chrome', 'MSIE 11'], trim($m[1], '/ ')." $m[2]").(!empty ($os) ? " ⋅ $os" : "");
			elseif (preg_match('~\b(AppleWebKit/)([0-9]+)~', $agent, $m))
				$agent = trim($m[1], '/')." $m[2]".(!empty ($os) ? " ⋅ $os" : "");

		return $agent;
	}

}
