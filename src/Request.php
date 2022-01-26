<?php
namespace Subframe;

use Exception;

class Request implements RequestInterface {

	/** @var string */
	private $method;

	/** @var string */
	private $uri;

	/** @var array */
	private $get;

	/** @var array */
	private $post;

	/** @var array */
	private $cookie;

	/** @var array */
	private $files;

	/** @var array */
	private $params;

	/**
	 * Creates a request for the given request parameters or for the current request
	 * @param string|null $method HTTP method/verb
	 * @param string|null $uri requested URI; if not specified, it's resolved automatically — from REQUEST_URI, but relative to the script's directory (to also take care of small websites that reside in a subdirectory)
	 * @throws Exception
	 */
	public function __construct(string $method, string $uri, array $get = [], array $post = [], array $cookie = [], array $files = [], array $params = []) {
		$this->method = $method;
		$this->uri = $uri;
		$this->get = $get;
		$this->post = $post;
		$this->cookie = $cookie;
		$this->files = [];
		$this->params = $params;

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

			if ($params['CONTENT_LENGTH'] > 0 && !$post && !$files)
				throw new Exception("Total upload size exceeds limit ($post_max_size).", 400);

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
	 * Creates a request for the request from the REQUEST_URI constant
	 * @return Request
	 * @throws Exception
	 */
	public static function fromRequestUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getRequestUri(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	/**
	 * Creates a request for the request from the REQUEST_URI constant, relative to the script's directory
	 * @return Request
	 * @throws Exception
	 */
	public static function fromRelativeRequestUri(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getRelativeRequestUri(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	/**
	 * Creates a request for the request from the PATH_INFO constant
	 * @return Request
	 * @throws Exception
	 */
	public static function fromPathInfo(): self {
		return new self($_SERVER['REQUEST_METHOD'] ?? 'GET', self::getPathInfo(), $_GET, $_POST, $_COOKIE, $_FILES, $_SERVER);
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getUri(): string {
		return $this->uri;
	}

	public function get(?string $name = null) {
		return (isset($name) ? $this->get[$name] ?? null : $this->get);
	}

	public function post(?string $name = null) {
		return (isset($name) ? $this->post[$name] ?? null : $this->post);
	}

	public function file(string $name) {
		return $this->files[$name] ?? null;
	}

	/**
	 * Obtains uploaded files array in a normalized form
	 * @return array
	 * @throws Exception
	 */
	public function getUploadedFiles(): array {
		return $this->files;
	}

	/**
	 * Remote address the request was made from
	 * @return string
	 */
	public function getRemoteAddr(): string {
		if (!empty($this->params['HTTP_X_FORWARDED_FOR']))
			foreach (explode(",", $this->params['HTTP_X_FORWARDED_FOR']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $this->params['REMOTE_ADDR'];
	}

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public function isAjax(): bool {
		return ($this->params['HTTP_X_REQUESTED_WITH'] ?? '') == 'XMLHttpRequest';
	}

	/**
	 * Tells whether JSON format is requested (in Accept header field)
	 * @return boolean
	 */
	public function acceptsJson(): bool {
		return strpos($this->params['HTTP_ACCEPT'], '/json') !== false;
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

}