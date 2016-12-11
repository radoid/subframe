<?php
/**
 * Implements the controller mechanism in MVC paradigm
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Controller
{
	protected $argv, $controller, $action, $args, $controllers, $views, $cache;

	function __construct($controllers = "./", $views = "./", $cache = null, $argv = null) {
		$this->argv = $argv;
		$this->controllers = $controllers;
		$this->views = $views;
		$this->cache = $cache;

		if (method_exists($this, 'init'))
			$this->init();
	}

	static function action($controllers = "./", $views = "./", $cache = null, $argv = null) {
		if (!$argv)
			$argv = self::argv();
		if (is_string($argv))
			$argv = explode('/', $argv);

		$gzip = (strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") !== false && extension_loaded('zlib') ? ".gz" : "");

		$cachename = ($cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output" . str_replace("/", "-", self::path_info()) . ".html$gzip" : false);
		if ($cachename) {
			if (!empty ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				if ($cache->ready($cachename, strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
					if (function_exists("header_remove"))
						header_remove();
					header('HTTP/1.0 304 Not Modified');
					exit;
				}
			}
			header("Last-Modified: " . date("r"));
			header("Vary: Accept-Encoding");
			if ($cache->ready($cachename)) {
				if ($gzip) {
					ini_set('zlib.output_compression', 'Off');
					header('Content-Encoding: gzip');
				}
				$cache->dump($cachename);
				exit;
			}
		}

		$method = strtolower($_SERVER['REQUEST_METHOD']);
		if (($classname = ucfirst(@$argv[1]))
				&& is_file($controllers . "$classname.php")
				&& (include_once $controllers . "$classname.php")
				&& class_exists($classname, false)
				&& ($instance = new $classname($controllers, $views, $cache, $argv))
				&& (method_exists($instance, $action = $method.ucfirst(@$argv[2]))
						|| method_exists($instance, $action = @$argv[2])
						|| (count($argv) > 3 && method_exists($instance, $method.ucfirst($argv[3])) && list($action, $argv[3]) = array($method.ucfirst($argv[3]), $argv[2]))
						|| (count($argv) > 3 && method_exists($instance, $argv[3]) && list($action, $argv[3]) = array($argv[3], $argv[2]))
						|| (count($argv) >= 3 && method_exists($instance, $method) && !array_splice($argv, 2, 0, $action = $method))
						|| method_exists($instance, "__call")
		)) {
			if ($cachename)
				ob_start();
			try {
				call_user_func_array(array($instance, $action), array_slice($argv, 3));
			} catch (\Exception $e) {
				header('HTTP/1.1 500 Server Error');
				trigger_error(htmlspecialchars($e), E_USER_ERROR);
			}
			if ($cachename)
				$cache->put($cachename, $gzip ? gzencode(ob_get_contents()) : ob_get_contents());
			exit;
		}
	}

	static function route($uri, callable $callable) {
		if (preg_match("~^$uri/?$~i", self::path_info(), $m)) {
			call_user_func_array($callable, array_slice($m, 1));
			exit;
		}
	}

	protected function view($_view, $_data = array(), $_status = 200) {
		$_controller = @$this->argv[1];
		$_action = @$this->argv[2];
		extract((array)$_data);
		if (is_string($_status))
			@header("HTTP/1.1 $_status");
		elseif (is_numeric($_status) && $_status != 200)
			@header("HTTP/1.1 $_status Unexpected Error");
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE);
		$include_path = set_include_path($this->views);
		(include "$_view.php")
			or trigger_error("View $_view was not found.", E_USER_ERROR);
		set_include_path($include_path);
		error_reporting($error_reporting);
	}

	protected function ob_view($_view, $_data = array()) {
		ob_start ();
		$this->view($_view, $_data);
		return ob_get_clean ();
	}

	protected function json($data = array(), $status = 200) {
		if (is_string($status))
			@header("HTTP/1.1 $status");
		elseif ($status && $status != 200)
			@header("HTTP/1.1 $status Unexpected Error");
		if ($status)
			@header("Content-Type: application/json; charset=utf-8");
		echo json_encode($data);
	}

	/**
	 * Fetches the PATH_INFO system variable
	 * @return string the variable
	 */
	static function path_info() {
		return str_replace($_SERVER['SCRIPT_NAME'], "", (!empty ($_SERVER['PATH_INFO']) ? $_SERVER['PATH_INFO'] : (!empty ($_SERVER['ORIG_PATH_INFO']) ? $_SERVER['ORIG_PATH_INFO'] : "/")));
	}

	/**
	 * Fetches current script arguments in MVC fashion
	 * @return array Array with the script name, controller name, action name, then arguments from path info, then GET arguments
	 */
	static function argv() {
		global $argv;
		$path_info = self::path_info();
		return ($path_info ? explode('/', rtrim($path_info, '/')) : (isset ($argv) ? $argv : array())) + array("", "home", "index");
	}

	static function redirect($url, $status = 302) {
		if ($status == 301)
			header("HTTP/1.1 301 Moved Permanently");
		header("Location: " . str_replace("\n", "\0", $url));
		exit;
	}

	static function isAjax() {
		return (@$_SERVER['HTTP_X_REQUESTED_WITH'] == "XMLHttpRequest");
	}

	protected function log($text, $extended = false, $filename = "debug.log") {
		$text = date("j.m.Y. H:i:s") . "  " . (is_string($text) ? $text : json_encode($text));
		if ($extended)
			$text .= " • " . self::remote_addr() . " • " . self::user_agent();
		//	$text .= @" • ← $_SERVER[HTTP_REFERER]";
		file_put_contents($filename, "$text\n", FILE_APPEND);
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
		elseif (preg_match('~\b(Mac OS X [0-9_.]+|Windows NT [0-9.]+|Windows 9\d+|Android\b ?[0-9.]*|Linux \w+|Windows Phone O?S? ?[0-9.]+|J2ME\b)~', $agent, $m))
			$os = strtr($m[1], ['Windows NT 5.1' => 'Windows XP', 'Windows NT 6.0' => 'Windows Vista', 'Windows NT 6.1' => 'Windows 7', 'Windows NT 6.2' => 'Windows 8', 'Windows NT 6.3' => 'Windows 8.1']);
		if (!empty ($os))
			if (preg_match('~\b(Opera/|Firefox/|Chrome/|CriOS/|Safari/|MSIE |Trident/)([0-9]+)~', $agent, $m))
				$agent = str_replace(['CriOS', 'Trident 7'], ['Chrome', 'MSIE 11'], trim($m[1], '/ ')." $m[2]").(!empty ($os) ? " ⋅ $os" : "");
			elseif (preg_match('~\b(AppleWebKit/)([0-9]+)~', $agent, $m))
				$agent = trim($m[1], '/')." $m[2]".(!empty ($os) ? " ⋅ $os" : "");

		return $agent;
	}

}
