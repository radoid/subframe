<?php
namespace Subframe;

use Exception;

/**
 * Represents an HTTP request
 */
class Request {

	/**
	 * The HTTP method
	 */
	private string $method;

	/**
	 * The path
	 */
	private string $path;

	/**
	 * The query string
	 */
	private string $queryString;

	/**
	 * The header fields
	 */
	private array $headers;

	/**
	 * The query (GET) parameters
	 */
	private array $queryParams;

	/**
	 * The POST parameters
	 */
	private array $parsedBody;

	/**
	 * The cookies
	 */
	private array $cookies;

	/**
	 * The uploaded files
	 */
	private array $files;

	/**
	 * The optional parameters from the global $_SERVER array
	 */
	private array $serverParams;


	/**
	 * Creates a request with all the input parameters already parsed and optional server parameters
	 * @param string $method HTTP method/verb
	 * @param string $pathAndQueryString The requested path; it should be appended by the query string if it wasn't parsed
	 * @param array $headers Associated array with header fields as keys and values
	 * @param array $get Associated array with parsed GET parameters (like $_GET)
	 * @param array $post Associated array with parsed POST parameters (like $_POST)
	 * @param array $files Associated array with uploaded files (like $_FILES)
	 * @param array $files Associated array with cookies (like $_COOKIE)
	 * @param array $files Associated array with server parameters (like $_SERVER)
	 */
	public function __construct(string $method, string $pathAndQueryString, array $headers = [], array $get = [], array $post = [], array $files = [], array $cookie = [], array $server = []) {
		$this->method = $method;
		$this->path = '/' . trim(strtok($pathAndQueryString, '?'), '/');
		$this->queryString = strtok('?');
		$this->queryParams = $get;
		$this->parsedBody = $post;
		$this->cookies = $cookie;
		$this->files = $files;
		$this->serverParams = $server;

		$this->headers = [];
		foreach ($headers as $name => $value)	
			$this->headers[strtolower($name)] = $value;
	}

	/**
	 * Creates a request from $_SERVER['REQUEST_URI'] and other superglobals;
	 * parses the body in case of a JSON POST or PUT request;
	 * throws an Exception if a POST or PUT request exceeds `post_max_size` server limit or the sending was interrupted
	 * @return Request
	 * @throws Exception
	 */
	public static function fromGlobalRequestUri(): self {
		$method = $_SERVER['REQUEST_METHOD'];
		$pathAndQueryString = rawurldecode($_SERVER['REQUEST_URI']);
		$headers = getallheaders();
		$files = self::getGlobalFiles();
		$contentLength = $headers['Content-Length'] ?? 0;

		if ($method == 'POST' || $method == 'PUT') {
			$maxPostSize = ini_get('post_max_size');
			$maxPostSize = intval($maxPostSize) * (['K' => 1024, 'M' => 1024**2, 'G' => 1024**3][substr($maxPostSize, -1)] ?? 1);
			if ($contentLength > $maxPostSize)
				throw new Exception("Total upload size exceeds server limit ($maxPostSize bytes).", 413);
			
			if (!$_POST && strpos($headers['Content-Type'] ?? '', 'application/json') === 0) {
				$body = file_get_contents('php://input');
				if (strlen($body) < $contentLength)
					throw new Exception('Sent data is incomplete.', 400);
				$parsedBody = json_decode($body, true);
			}
		}

		return new self($method, $pathAndQueryString, $headers, $_GET, $parsedBody ?? $_POST, $files, $_COOKIE, $_SERVER);
	}

	/**
	 * Creates a request from the global $argv array
	 * @return Request
	 */
	public static function fromGlobalArgv(): self {
		global $argv;
		$path = join('/', [''] + $argv);
		
		return new self('GET', $path, [], [], [], [], [], $_SERVER);
	}

	/**
	 * Request's HTTP method
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Request's path, with leading slash, but without any trailing slash or query parameters
	 */
	public function getPath(): string {
		return $this->path;
	}

	/**
	 * Request's query string, without leading question mark
	 */
	public function getQueryString(): string {
		return $this->queryString;
	}

	/**
	 * Request's path and query string
	 */
	public function getPathAndQueryString(): string {
		return $this->path . ($this->queryString !== '' ? '?' : '') . $this->queryString;
	}

	/**
	 * Specific header field's value, or all fields as an associative array
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getHeader(?string $name = null) {
		if (isset($name))
			return $this->headers[strtolower($name)] ?? null;
		else
			return $this->headers;
	}

	/**
	 * Returns a query (GET) parameter by name, or all parameters
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getQuery(?string $name = null) {
		if (isset($name))
			return $this->queryParams[$name] ?? null;
		else
			return $this->queryParams;
	}

	/**
	 * Returns a (POST) variable from the body by name, or all variables
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getPost(?string $name = null) {
		if (isset($name))
			return $this->parsedBody[$name] ?? null;
		else
			return $this->parsedBody;
	}

	/**
	 * Returns a cookie value, if present in the request
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function getCookie(?string $name = null) {
		if (isset($name))
			return $this->cookies[$name] ?? null;
		else
			return $this->cookies;
	}

	/**
	 * Returns a parameter from the $_SERVER array, if present in the request
	 * @param string|null $name
	 * @return string|array|null
	 */
	public function getServer(?string $name = null) {
		if (isset($name))
			return $this->serverParams[$name] ?? null;
		else
			return $this->serverParams;
	}

	/**
	 * The hostname, taken from the Host header field
	 * @return string|null
	 */
	public function getHost(): ?string {
		return $this->headers['host'] ?? null;
	}

	public function getSchemeAndHost(): ?string {
		$scheme = (!empty($this->serverParams['HTTPS']) ? 'https://' : 'http://');
		
		return $scheme . $this->getHost();
	}

	/**
	 * Returns an array representing the named uploaded file, or all files
	 * @return array|null
	 */
	public function getFiles(?string $name = null): ?array {
		$this->handleFileErrors($this->files);

		return (isset($name) ? $this->files[$name] ?? null : $this->files);
	}

	/**
	 * Checks if there are any errors during upload
	 * @throws Exception
	 */
	private function handleFileErrors(array &$files): void {
		foreach ($files as $varname => &$file)
			if (key_exists('error', $file)) {
				if ($file['error'] == UPLOAD_ERR_NO_FILE)
					unset($files[$varname]);
				elseif ($file['error'] == UPLOAD_ERR_INI_SIZE)
					throw new Exception("File \"$file[name]\" exceeds size limit.", 413);
				elseif ($file['error'])
					throw new Exception("Upload failed (error #$file[error]).", 400);
			} else
				$this->handleFileErrors($file);
	}

	/**
	 * The remote address the request was made from, taking X-Forwarded-For header field into accout
	 * @return string|null
	 */
	public function getRemoteAddr(): ?string {
		if (!empty($this->headers['x-forwarded-for']))
			foreach (explode(",", $this->headers['x-forwarded-for']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $this->serverParams['REMOTE_ADDR'] ?? null;
	}

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public function isXmlHttpRequest(): bool {
		return ($this->headers['x-requested-with'] ?? '') == 'XMLHttpRequest';
	}

	/**
	 * Tells whether JSON format is requested (in Accept header field)
	 * @return boolean
	 */
	public function acceptsJson(): bool {
		return strpos($this->headers['accept'] ?? '', '/json') !== false;
	}

	/**
	 * Obtains the list of uploaded files; normalizes multidimensional arrays of files; skips files that are not specified
	 */
	private static function getGlobalFiles(): array {
		$files = [];
		foreach ($_FILES as $key => $file)
			if (isset($file['name']) && is_array($file['name'])) {
				$new = [];
				foreach (['name', 'full_path', 'type', 'tmp_name', 'error', 'size'] as $k)
					if (key_exists($k, $file)) {
						array_walk_recursive($file[$k], function (&$data, $key, $k) { $data = [$k => $data]; }, $k);
						$new = array_replace_recursive($new, $file[$k]);
					}
				$files[$key] = $new;
			} else
				$files[$key] = $file;
	
		return $files;
	}

	/**
	 * Obtains the User-Agent string, processed into simple browser and OS name and version
	 */
	public function getUserAgent(): ?string {
		$agent = $this->headers['user-agent'] ?? null;
		if ($agent)
			if (preg_match('~\b(iPad|iPhone|iPod); U?;? ?CPU (OS|iPhone OS) ([0-9]+)~', $agent, $m))
				$os = str_replace('_', '.', "$m[1] iOS $m[3]");
			elseif (preg_match('~\b(Mac OS X [0-9_.]+|Windows NT [0-9.]+|Windows 9\d+|Android\b ?[0-9.]*|Linux \w+|Windows Phone O?S? ?[0-9.]+|J2ME\b)~', $agent, $m))
				$os = strtr($m[1], ['Mac OS X' => 'macOS', 'Windows NT 5.1' => 'Windows XP', 'Windows NT 6.0' => 'Windows Vista', 'Windows NT 6.1' => 'Windows 7', 'Windows NT 6.2' => 'Windows 8', 'Windows NT 6.3' => 'Windows 8.1', 'Windows NT 10.0' => 'Windows 10']);
		if (!empty($os)) {
			if (preg_match('~\b(Opera/|Firefox/|Chrome/|CriOS/|Safari/|MSIE |Trident/)([0-9]+)~', $agent, $m))
				$agent = str_replace(['CriOS', 'Trident 7'], ['Chrome', 'MSIE 11'], trim($m[1], '/ ')." $m[2]").(!empty ($os) ? " • $os" : "");
			elseif (preg_match('~\b(AppleWebKit/)([0-9]+)~', $agent, $m))
				$agent = trim($m[1], '/')." $m[2]".(!empty ($os) ? " • $os" : "");
			if (strpos($os, 'Android') === 0)
				$agent = str_replace('Safari', 'Android browser', $agent);
		}

		return $agent;
	}

	/**
	 * Changes the path according to replacement rules, written as regular expressions
	 */
	public function rewrite(array $rules): self {
		foreach ($rules as $pattern => $replacement)
			if (($path = preg_replace("#^(?:$pattern)$#i", $replacement, $this->path)) != $this->path) {
				$this->path = '/' . trim($path, '/');
				break;
			}

		return $this;
	}

}