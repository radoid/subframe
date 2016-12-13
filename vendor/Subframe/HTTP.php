<?php
/**
 * HTTP communication with remote servers
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class HTTP
{
	private $host, $ssl, $port, $debug, $f, $errno, $error;

	function __construct($host, $port = 0, $ssl = false, $debug = "") {
		$this->host = $host;
		$this->ssl = $ssl;
		$this->port = ($port ? $port : ($ssl ? 443 : 80));
		$this->debug = $debug;
	}

	function connect($timeout = 20) {
		if ($this->debug) echo("Connecting to $this->host...");
		$this->f = fsockopen(($this->ssl ? "ssl://" : "") . $this->host, $this->port, $this->errno, $this->error, $timeout);
		if ($this->f)
			stream_set_blocking($this->f, false);
		if ($this->debug) echo($this->f ? "Connected.\n" : "$this->error ($this->errno)");
	}

	function isConnected() {
		return ($this->f ? true : false);
	}

	function error() {
		return $this->error;
	}

	function errno() {
		return $this->errno;
	}

	/**
	 * Closes the socket
	 */
	function close() {
		return @fclose($this->f);
	}

	/**
	 * Lower-level writing function, intended for REST communication
	 */
	function write($data) {
		if (!isset ($this->f))
			$this->connect();
		if (!$this->f)
			return false;
		if ($this->debug) echo("=== Sending: ===\n$data\n===============\n");
		return (fwrite($this->f, $data) ? true : false);
	}

	/**
	 * Lower-level reading function, intended for REST communication
	 * @return string response or FALSE if timed out or disconnected before any response
	 */
	function read($timeout = 20, $timeafter = 2) {
		if (!isset ($this->f))
			$this->connect();
		if (!$this->f)
			return false;
		$timeout = time() + $timeout;
		while (!feof($this->f) && time() <= $timeout && ($packet = fread($this->f, 8192)) !== false) {
			if ($packet !== "") {
				if ($this->debug) echo("> $packet");
				$data = (isset ($data) ? $data : "") . $packet;
				$timeout = time() + $timeafter;
			} else
				usleep(100000);
		}
		return (isset ($data) ? $data : false);
	}

	/**
	 * Higher-level writing function, intended for requests to web-servers
	 * @param string $method Naredba GET ili POST
	 * @param string $uri Adresa sadržaja na koji se odnosi naredba
	 * @param string $headers Možebitni headeri
	 * @param array|string $data Možebitni podaci formulara ili string posebne naredbe
	 * @param array $cookie Možebitni cookieji
	 * @return boolean Uspjeh
	 */
	function request($method, $uri, $content = "", $headers = "", $cookie = array()) {
		if (!isset ($this->f))
			$this->connect();
		if (!$this->f)
			return false;
		$request = "$method $uri HTTP/1.0\r\nHost: $this->host\r\nConnection: close\r\n";
		if (is_array($content) || is_object($content)) {
			$content = self::urlencoded($content);
			$request .= "Content-Type: application/x-www-form-urlencoded\r\n";
		}
		$request .= "Content-Length: " . strlen($content) . "\r\n";
		if ($cookie && is_array($cookie))
			$cookie = self::urlencoded($cookie);
		if ($cookie)
			$request .= "Cookie: $cookie\r\n";
		if ($headers && (is_array($headers) || is_object($headers)))
			$headers = self::lineencoded($headers);
		if ($headers)
			$request .= trim($headers) . "\r\n";
		$request .= "\r\n" . trim($content);
		return $this->write($request);
	}

	/**
	 * Higher-level reading function, intended for responses from web-servers
	 */
	function response($timeout = 15, $timeafter = 2) {
		if (!isset ($this->f))
			$this->connect();
		if (!$this->f)
			return false;
		if (($data = $this->read($timeout, $timeafter)) === false)
			return false;
		$response = array('headers' => array(), 'cookie' => array(), 'content' => "");
		for ($i = $p = 0; ($p2 = strpos($data, "\n", $p)) && ($line = trim(substr($data, $p, $p2 - $p))); $i++, $p = $p2 + 1) {
			if ($i == 0 && preg_match('~HTTP/[\d.]+\s+(\d+)\s*(.*)~', $line, $m)) {
				$response['status'] = "$m[1] $m[2]";
				$response['statusCode'] = intval($m[1]);
				$response['statusText'] = $m[2];
			} elseif (count(@list ($key, $value) = explode(": ", $line)) >= 2)
				if ($key == "Set-Cookie" && preg_match('~^([^=]+)=([^;]+)~', trim($value), $m))
					$response['cookie'][urldecode(trim($m[1]))] = urldecode($m[2]);
				else
					$response['headers'][trim($key)] = trim($value);
		}
		$response['content'] = substr($data, $p2 + 1);
		if (strpos(@$response['headers']['Transfer-Encoding'], "chunked") !== false)
			$response['content'] = preg_replace("/\r?\n[0-9abcdef]+\r?\n/", '', $response['content']); // TODO

		return $response;
	}

	static function get($url, $headers = "", $cookie = array()) {
		if (!($url = self::parse_url($url)))
			return false;
		$http = new self ($url['host'], $url['port'] ? $url['port'] : 0, $url['scheme'] == "https");
		if (!$http->request("GET", $url['path'] . ($url['query'] ? "?$url[query]" : ""), "", $headers, $cookie))
			return false;
		return $http->response();
	}

	static function post($url, $data = array(), $headers = "", $cookie = array()) {
		if (!($url = self::parse_url($url)))
			return false;
		$http = new self ($url['host'], $url['port'] ? $url['port'] : 0, $url['scheme'] == "https");
		if (!$http->request("POST", $url['path'] . ($url['query'] ? "?$url[query]" : ""), $data ? $data : "", $headers, $cookie))
			return false;
		return $http->response();
	}

	static function urlencoded(array $data) {
		$encoded = "";
		foreach ($data as $key => $value)
			$encoded .= "&" . rawurlencode($key) . "=" . rawurlencode($value);
		// TODO
		return $encoded;
	}

	private static function lineencoded(array $data) {
		$encoded = "";
		foreach ($data as $key => $value)
			$encoded .= "$key: $value\r\n";
		return $encoded;
	}

	static function parse_url($url, $context = "") {
		if ($url === "") // prazan URL znači ostanak na istoj adresi
			return ($context ? $context : false);
		$scheme = $host = $user = $pass = $port = $path = $query = $fragment = "";
		if (preg_match('~^(\w+:|)//([^/]+)(/.*|)$~', $url, $m)) { // kontekstno neovisna adresa
			@list (, $scheme, $host, $path) = $m;
			$scheme = trim($scheme, ':');
			if (preg_match('~^(\w+@|\w+:[^@]*@|)([^:/]+)(:\d+|)$~', $host, $m)) {
				@list (, $user, $host, $port) = $m;
				$port = trim($port, ":");
				@list ($user, $pass) = explode(":", trim($user, "@"));
			}
			$link = array('scheme' => $scheme, 'host' => $host, 'user' => $user, 'pass' => $pass, 'port' => $port, 'path' => $path);
		} elseif (($link = self::parse_url($context))) { // kontekstno ovisna adresa
			if (@$url[0] == '/')
				$link['path'] = $url;
			else
				$link['path'] = preg_replace('#/?[^/]*$#', "", $link['path']) . "/" . $url;
		} else
			return false;
		@list ($link['path'], $link['fragment']) = explode("#", $link['path']);
		@list ($link['path'], $link['query']) = explode("?", $link['path']);
		$link['path'] = "/" . ltrim($link['path'], "/");
		$link['path'] = preg_replace('~[^/]*/\.\.(/|$)~', "", $link['path']);
		$link['dir'] = preg_replace('~/?[^/]*$~', "", $link['path']) . "/";
		$link['file'] = preg_replace('~^.*/~', "", $link['path']);
		$link['complete'] = ($link['scheme'] ? "$link[scheme]:" : "")
				. ($link['host'] ? "//" . ($link['user'] ? "$link[user]" . ($link['pass'] ? ":$link[pass]" : "") . "@" : "") . $link['host'] . ($link['port'] ? ":$link[port]" : "") : "")
				. $link['path']
				. ($link['query'] ? "?$link[query]" : "")
				. ($link['fragment'] ? "#$link[fragment]" : "");
		return $link;
	}

	static function complete_url($url, $context = "") {
		$parts = self::parse_url($url, $context);
		return ($parts && $parts['complete'] ? $parts['complete'] : $url);
	}

}