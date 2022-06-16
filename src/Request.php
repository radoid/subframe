<?php
namespace Subframe;

use Exception;

/**
 * Represents an HTTP request
 */
class Request implements RequestInterface {

	/**
	 * The request's HTTP method
	 * @var string
	 */
	private $method;

	/**
	 * The request's URI
	 * @var string
	 */
	private $uri;

	/**
	 * The request's HTTP header fields
	 * @var array
	 */
	private $headers;

	/**
	 * The query (GET) parameters
	 * @var array
	 */
	private $queryParams;

	/**
	 * The POST variables
	 * @var array
	 */
	private $parsedBody;

	/**
	 * The cookies
	 * @var array
	 */
	private $cookies;

	/**
	 * The uploaded files
	 * @var array
	 */
	private $files;

	/**
	 * The optional parameters usually present in the $_SERVER array
	 * @var array
	 */
	private $serverParams;


	/**
	 * Creates a request for the given request parameters or for the current request
	 * @param string|null $method HTTP method/verb
	 * @param string|null $uri requested URI; if not specified, it's resolved automatically — from REQUEST_URI, but relative to the script's directory (to also take care of small websites that reside in a subdirectory)
	 * @throws Exception
	 */
	public function __construct(string $method, string $uri, array $get = [], array $post = [], array $cookie = [], array $files = [], array $server = [], array $headers = []) {
		$this->method = $method;
		$this->uri = $uri;
		$this->headers = $headers;
		$this->queryParams = $get;
		$this->parsedBody = $post;
		$this->cookies = $cookie;
		$this->files = [];
		$this->serverParams = $server;

		foreach ($server as $key => $value)
			if (substr($key, 0, 5) == 'HTTP_')
				$this->headers[self::capitalizeName(strtr(substr($key, 5), '_', '-'))] = $value;
		$this->headers['Content-Type'] = $server['CONTENT_TYPE'] ?? null;
		$this->headers['Content-Length'] = $server['CONTENT_LENGTH'] ?? null;

		foreach ($files as $varname => $file)
			if (is_array($file['name'])) {
				foreach ($file as $key => $array)
					foreach ($array as $i => $value)
						if ($file['error'][$i] != UPLOAD_ERR_NO_FILE)
							$this->files[$varname][$i][$key] = $value;
			} else
				if ($file['error'] != UPLOAD_ERR_NO_FILE)
					$this->files[$varname][] = $file;

		if ($method == 'POST') {
			$upload_max_filesize = ini_get('upload_max_filesize');
			$post_max_size = ini_get('post_max_size');

			if (($server['CONTENT_LENGTH'] ?? 0) > 0 && !$post && !$files)
				throw new Exception("Total upload size exceeds limit ($post_max_size).", 400);

			foreach ($this->files as $varname => $files)
				foreach ($files as $file)
					if ($file['error'])
						switch ($file['error']) {
							case UPLOAD_ERR_INI_SIZE:
								throw new Exception("\"$file[name]\" exceeds size limit ($upload_max_filesize).", 400);
							case UPLOAD_ERR_FORM_SIZE:
								throw new Exception("\"$file[name]\" is too big.", 400);
							case UPLOAD_ERR_PARTIAL:
								throw new Exception("\"$file[name]\" was not uploaded.", 500);
							case UPLOAD_ERR_NO_TMP_DIR:
								throw new Exception('No tmp directory.', 500);
							case UPLOAD_ERR_CANT_WRITE:
								throw new Exception('Write error.', 500);
							case UPLOAD_ERR_EXTENSION:
								throw new Exception('File extension is not allowed.', 500);
							default:
								throw new Exception("Upload failed (error $file[error]).", 500);
						}
		}
	}

	/**
	 * Creates a request for the request from the $_SERVER['REQUEST_URI'] variable
	 * @return Request
	 * @throws Exception
	 */
	public static function fromRequestUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getRequestUri(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	/**
	 * Creates a request for the request from the $_SERVER['REQUEST_URI'] variable, but relative to the index.php script's directory
	 * @return Request
	 * @throws Exception
	 */
	public static function fromRelativeRequestUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getRelativeRequestUri(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	/**
	 * Creates a request for the request from the $_SERVER['PATH_INFO'] variable
	 * @return Request
	 * @throws Exception
	 */
	public static function fromPathInfo(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getPathInfo(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	/**
	 * Request's HTTP method
	 * @return string
	 */
	public function getMethod(): string {
		return $this->method;
	}

	/**
	 * Request's URI
	 * @return string
	 */
	public function getUri(): string {
		return $this->uri;
	}

	/**
	 * All header fields in the request
	 * @return string[]
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Specific header field's value
	 * @param string $name
	 * @return string|null
	 */
	public function getHeader(string $name): ?string {
		return $this->headers[self::capitalizeName($name)] ?? null;
	}

	/**
	 * All query (GET) parameters in the request
	 * @return string[]
	 */
	public function getQueryParams(): array {
		return $this->queryParams;
	}

	/**
	 * All (POST) variables from the request's body
	 * @return string[]
	 */
	public function getParsedBody(): array {
		return $this->parsedBody;
	}

	/**
	 * Returns a query (GET) parameter by name
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function get(?string $name = null) {
		return (isset($name) ? $this->queryParams[$name] ?? null : $this->queryParams);
	}

	/**
	 * Returns a (POST) variable from the body by name
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function post(?string $name = null) {
		return (isset($name) ? $this->parsedBody[$name] ?? null : $this->parsedBody);
	}

	/**
	 * Returns a cookie value, if present in the request
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function cookie(?string $name = null) {
		return (isset($name) ? $this->cookies[$name] ?? null : $this->cookies);
	}

	/**
	 * Returns an uploaded file definition by name
	 * @param string|null $name
	 * @return string|string[]|null
	 */
	public function file(string $name): ?array {
		return $this->files[$name] ?? null;
	}

	/**
	 * All uploaded files definitions in a normalized form
	 * @return array
	 * @throws Exception
	 */
	public function getUploadedFiles(): array {
		return $this->files;
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
	public function isAjax(): bool {
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
	 * @return string
	 */
	public static function getRequestUri(): string {
		global $argv;
		if (isset($_SERVER['REQUEST_URI']))
			$uri = rawurldecode(strtok($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'], '?'));
		else
			$uri = '/'.join('/', array_slice($argv, 1));

		return $uri;
	}

	/**
	 * Obtains the current request URI, relative to the directory the script is in, unless in case of a shell script
	 * @return string
	 */
	public static function getRelativeRequestUri(): string {
		$uri = self::getRequestUri();
		$subdir = dirname($_SERVER['SCRIPT_NAME']);
		if (isset($_SERVER['REQUEST_URI']) && $subdir != '.')
			$uri = substr($uri, strlen($subdir));

		return $uri;
	}

	/**
	 * Obtains the current PATH_INFO variable; in case of a shell script, it's built from the script's arguments
	 * @return string
	 */
	public static function getPathInfo(): string {
		global $argv;
		if (isset($_SERVER['REQUEST_METHOD'])) {
			$uri = rawurldecode($_SERVER['ORIG_PATH_INFO'] ?? $_SERVER['PATH_INFO'] ?? '/');
		} else
			$uri = '/'.join('/', array_slice($argv, 1));

		return $uri;
	}

	/**
	 * Capitalizes a header field name properly
	 */
	protected static function capitalizeName(string $name): string {
		return ucwords(strtolower($name), '-');
	}

}