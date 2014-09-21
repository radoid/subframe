<?php
/**
 * Implements the controller mechanism in MVC paradigm
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Controller
{
	protected $argv, $controller, $action, $args;
	static $controllers, $views, $cache;

	function __construct(array $argv = null) {
		$this->argv = $argv;
		/*$this->cache = self::$cache;
		$this->controllers = self::$controllers;
		$this->views = self::$views;*/
		//if (method_exists ($this, $this->action) || method_exists ($this, "__call")) /*&& is_callable (array ($classname, $action))*/) {
	}

	static function action($argv = null, $cache = null, $controllers = "", $views = "") {
		if (!$argv)
			$argv = self::argv();
		if (is_string($argv))
			$argv = explode('/', $argv);
		if ($controllers)
			self::$controllers = $controllers;
		if ($views)
			self::$views = $views;
		if ($cache)
			self::$cache = $cache;

		$gzip = (strpos(@$_SERVER['HTTP_ACCEPT_ENCODING'], "gzip") !== false && extension_loaded('zlib') ? ".gz" : "");

		$cachename = (self::$cache && @$_SERVER['REQUEST_METHOD'] == "GET" ? "output" . str_replace("/", "-", self::path_info()) . ".html$gzip" : false);
		if ($cachename) {
			if (!empty ($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
				if (self::$cache->ready($cachename, strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']))) {
					if (function_exists("header_remove"))
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

		//if ((class_exists ($classname, false) || ((@include_once self::$controllers."$classname.php") && class_exists ($classname)))
		if (((($classname = ucfirst($argv[1]) . "Controller") && is_file(self::$controllers . "$classname.php") && (include_once self::$controllers . "$classname.php"))
						|| (($classname = ucfirst($argv[1])) && is_file(self::$controllers . "$classname.php") && (include_once self::$controllers . "$classname.php")))
				&& class_exists($classname, false)
				&& ($instance = new $classname ($argv))
				&& (method_exists($instance, $action = "$argv[2]Action")
						|| method_exists($instance, $action = $argv[2])
						|| method_exists($instance, "__call")
						|| (count($argv) > 3 && method_exists($instance, $argv[3]) && list($action, $argv[3]) = array($argv[3], $argv[2]))
						|| (count($argv) >= 3 && method_exists($instance, 'single') && !array_splice($argv, 2, 0, $action = 'single')))
		) {
			//die("action: $action, args: ".json_encode(array_slice($argv, 3)));
			if ($cachename)
				ob_start();
			try {
				//if (method_exists ($instance, "before"))
				//	$instance->before ();
				call_user_func_array(array($instance, $action), array_slice($argv, 3));
				//if (method_exists ($instance, "after"))
				//	$instance->after ();
			} catch (Exception $e) {
				trigger_error(htmlspecialchars($e), E_USER_ERROR);
			}
			if ($cachename)
				self::$cache->put($cachename, $gzip ? gzencode(ob_get_contents()) : ob_get_contents());
			exit;
		}
	}

	function template($filename, $data = array(), $status = 200) {
		$this->view($filename, $data, $status);
	}

	function ob_template($filename, $data = array(), $status = 200) {
		return $this->ob_view($filename, $data, $status);
	}

	function view($_filename, $_data = array(), $_status = 200) {
		//$_controller = $page = @$this->argv[1]; //strtolower (get_called_class ());//
		//$_action = $subpage = @$this->argv[2]; //$this->action;
		if (is_array($_data))
			extract($_data);
		elseif (is_string($_data))
			$errors = array($_data);
		if (isset($errors) && is_string($errors))
			$errors = array($errors);
		if (is_string($_status))
			header("HTTP/1.1 $_status");
		elseif (is_numeric($_status) && $_status != 200)
			header("HTTP/1.1 $_status " . @reset($errors));
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE);
		//$include_path = set_include_path(self::$views);
		(include self::$views."$_filename.php")
			or trigger_error("View $_filename was not found.", E_USER_ERROR);
		//set_include_path($include_path);
		error_reporting($error_reporting);
	}

	function ob_view($view_filename, $view_data = array(), $view_status = 200) {
		ob_start ();
		$this->view($view_filename, $view_data);
		return ob_get_clean ();
	}

	function json($data = array(), $status = 200) {
		if (is_object($data))
			$data = (array)$data;
		elseif (!is_array($data))
			$data = array('errors' => array($data));
		if (isset ($data['errors']) && !is_array($data['errors']))
			$data['errors'] = array($data['errors']);
		if (is_string($status))
			@header("HTTP/1.1 $status");
		elseif ($status && $status != 200)
			@header("HTTP/1.1 $status " . ($data['errors'] ? str_replace("\n", "\0", reset($data['errors'])) : "Error"));
		if ($status)
			@header("Content-Type: application/json; charset=utf-8");
		echo json_encode($data + array('timestamp' => time(), 'errors' => array()));
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

	static function redirect($url) {
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

	static function user_agent ($agent = "") {
		$agent = $agent ? $agent : $_SERVER['HTTP_USER_AGENT'];
		if (preg_match ('~\b(iPad|iPhone); U?;? ?CPU (OS|iPhone OS) ([0-9]+)~', $agent, $m))
			$os = str_replace ('_', '.', "$m[1] iOS $m[3]");
		elseif (preg_match ('~\b(Mac OS X [0-9_.]+|Windows NT [0-9.]+|Windows 9\d+|Android\b ?[0-9.]*|Linux \w+|Windows Phone O?S? ?[0-9.]+|J2ME\b)~', $agent, $m))
			$os = strtr ($m[1], array('Windows NT 5.1' => 'Windows XP', 'Windows NT 6.0' => 'Windows Vista', 'Windows NT 6.1' => 'Windows 7', 'Windows NT 6.2' => 'Windows 8', 'Windows NT 6.3' => 'Windows 8.1'));
		if (!empty ($os))
			if (preg_match ('~\b(Opera/|Firefox/|Chrome/|CriOS/|Safari/|MSIE |Trident/)([0-9]+)~', $agent, $m))
				$agent = str_replace('CriOS', 'Chrome', trim ($m[1], '/ ')) . " $m[2]" . (!empty ($os) ? " ⋅ $os" : "");
			elseif (preg_match ('~\b(AppleWebKit/)([0-9]+)~', $agent, $m))
				$agent = trim ($m[1], '/') . " $m[2]" . (!empty ($os) ? " ⋅ $os" : "");

		return $agent;
	}

}
