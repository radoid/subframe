<?php
namespace Subframe;

use Exception;

/**
 * Represents an HTTP request
 */
class Request {

	/**
	 * The request's HTTP method
	 */
	private string $method;

	/**
	 * The request's URI
	 */
	private string $uri;

	/**
	 * The request's HTTP header fields
	 */
	private array $headers;

	/**
	 * The query (GET) parameters
	 */
	private array $queryParams;

	/**
	 * The POST variables
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
	 * The optional parameters usually present in the $_SERVER array
	 */
	private array $serverParams;


	/**
	 * Creates a request for the given request parameters or for the current request
	 * @param string $method HTTP method/verb
	 * @param string $uri requested URI
	 * @throws Exception
	 */
	public function __construct(string $method, string $uri, array $get = [], array $post = [], array $cookie = [], array $files = [], array $server = [], array $headers = []) {
		$this->method = $method;
		$this->uri = '/'.trim(strtok($uri, '?'), '/');
		$this->headers = $headers;
		$this->queryParams = $get;
		$this->parsedBody = $post;
		$this->cookies = $cookie;
		$this->files = $files;
		$this->serverParams = $server;
	}

	/**
	 * Creates a request for the request from the $_SERVER['REQUEST_URI'] variable
	 * @return Request
	 * @throws Exception
	 */
	public static function fromGlobalRequestUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getGlobalRequestUri(), $_GET, $_POST, $_COOKIE, self::getGlobalFiles(), $_SERVER, getallheaders());
	}

	/**
	 * Creates a request for the request from the $_SERVER['REQUEST_URI'] variable, but relative to the index.php script's directory
	 * @return Request
	 * @throws Exception
	 */
	public static function fromGlobalRelativeUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getGlobalRelativeUri(), $_GET, $_POST, $_COOKIE, self::getGlobalFiles(), $_SERVER, getallheaders());
	}

	/**
	 * Creates a request for the request from the $_SERVER['PATH_INFO'] variable
	 * @return Request
	 * @throws Exception
	 */
	public static function fromGlobalPathInfo(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getGlobalPathInfo(), $_GET, $_POST, $_COOKIE, self::getGlobalFiles(), $_SERVER, getallheaders());
	}

	/**
	 * Request's HTTP method
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Request's URI, including possible trailing slash and query parameters
	 */
	public function getUri(): string {
		return $this->uri;
	}

	/**
	 * Specific header field's value, or all fields as an associative array
	 * @param ?string $name
	 * @return string|array|null
	 */
	public function getHeader(?string $name = null) {
		if (isset($name)) {
			$name = ucwords(strtolower($name), '-');
			$name = ($name == 'Etag' ? 'ETag' : $name);
			return $this->headers[$name] ?? null;
		} else
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
	 * Returns an array representing the named uploaded file, or all files
	 * @return array|null
	 * @throws Exception
	 */
	public function getFiles(?string $name = null): ?array {
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size = ini_get('post_max_size');

		if ($this->method == 'POST' && ($this->serverParams['CONTENT_LENGTH'] ?? 0) > 0 && !$this->parsedBody && !$this->files)
			throw new Exception("Total upload size exceeds limit ($post_max_size).", 400);

		foreach ($this->files as $file)
			foreach ((key_exists('name', $file) ? [$file] : $file) as $file)
				if (!isset($name) || $file['name'] == $name)
					if ($file['error'] == UPLOAD_ERR_INI_SIZE)
						throw new Exception("\"$file[name]\" exceeds size limit ($upload_max_filesize).", 400);
					elseif ($file['error'])
						throw new Exception("Upload failed (error #$file[error]).", 500);

		return (isset($name) ? $this->files[$name] ?? null : $this->files);
	}

	/**
	 * Remote address the request was made from, taking X-Forwarded-For header field into accout
	 * @return string
	 */
	public function getRemoteAddr(): string {
		if (!empty($this->headers['X-Forwarded-For']))
			foreach (explode(",", $this->headers['X-Forwarded-For']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $this->serverParams['REMOTE_ADDR'] ?? '';
	}

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public function isXmlHttpRequest(): bool {
		return ($this->serverParams['HTTP_X_REQUESTED_WITH'] ?? '') == 'XMLHttpRequest';
	}

	/**
	 * Tells whether JSON format is requested (in Accept header field)
	 * @return boolean
	 */
	public function acceptsJson(): bool {
		return strpos($this->headers['Accept'] ?? '', '/json') !== false;
	}

	/**
	 * Obtains the current request URI; in case of a shell script, it's built from the script's arguments
	 */
	private static function getGlobalRequestUri(): string {
		global $argv;
		if (isset($_SERVER['REQUEST_URI']))
			$uri = rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'));
		else
			$uri = '/'.join('/', array_slice($argv, 1));

		return $uri;
	}

	/**
	 * Obtains the current request URI, relative to the directory the script is in, unless in case of a shell script
	 */
	private static function getGlobalRelativeUri(): string {
		$uri = self::getGlobalRequestUri();
		$subdir = dirname($_SERVER['SCRIPT_NAME']);
		if (isset($_SERVER['REQUEST_URI']) && $subdir != '.')
			$uri = substr($uri, strlen($subdir));

		return $uri;
	}

	/**
	 * Obtains the current PATH_INFO variable; in case of a shell script, it's built from the script's arguments
	 */
	private static function getGlobalPathInfo(): string {
		global $argv;
		if (isset($_SERVER['REQUEST_METHOD'])) {
			$uri = rawurldecode($_SERVER['PATH_INFO'] ?? '/');
		} else
			$uri = '/'.join('/', array_slice($argv, 1));

		return $uri;
	}

	/**
	 * Obtains the list of uploaded files
	 */
	private static function getGlobalFiles(): array {
		$files = [];
		foreach ($_FILES as $varname => $file)
			if (is_array($file['name'])) {
				foreach ($file as $key => $array)
					foreach ($array as $i => $value)
						if ($file['error'][$i] != UPLOAD_ERR_NO_FILE)
							$files[$varname][$i][$key] = $value;
			} else
				if ($file['error'] != UPLOAD_ERR_NO_FILE)
					$files[$varname] = $file;

		return $files;
	}

}