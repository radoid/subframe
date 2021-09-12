<?php
namespace Subframe;

use Throwable;

/**
 * Represents a response to an HTTP request
 */
class Response {

	/**
	 * HTTP status code
	 * @var int
	 */
	protected int $statusCode;

	/**
	 * HTTP header fields
	 * @var array
	 */
	protected array $headers;

	/**
	 * The body of the response
	 * @var string
	 */
	protected string $body;


	/**
	 * The constructor
	 */
	public function __construct(string $body, int $statusCode = 200, array $headers = []) {
		$this->statusCode = $statusCode;
		$this->body = $body;
		$this->headers = $headers;
	}

	/**
	 * Creates a response from a view
	 */
	public static function fromView(string $filename, array $data = [], int $statusCode = 200): self {
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
		ob_start();

		try {
			(function ($_filename, $_data) {
				extract($_data);
				require "$_filename.php";
			})($filename, $data);
		} catch (Throwable $exception) {}

		$output = ob_get_clean();
		error_reporting($error_reporting);

		if (isset($exception))
			throw $exception;
		
		return new Response($output, $statusCode, []);
	}

	/**
	 * Creates a JSON response from an array
	 * @param array|object $data The data
	 * @param int $statusCode The HTTP response code
	 */
	public static function fromJson($data = [], int $statusCode = 200): self {
		$content = json_encode((object)$data);
		$headers = [
			'Content-Type: application/json; charset=utf-8',
			'Content-Length: ' . strlen($content),
		];
		
		return new Response($content, $statusCode, $headers);
	}

	/**
	 * Constructs a Response with a "Location:" header field
	 */
	public static function fromRedirection(string $url, int $statusCode = 302): self {
		return new Response('', $statusCode, ["Location: $url"]);
	}

	/**
	 * Returns the response's status code
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * Sets the response's status code
	 */
	public function setStatusCode(int $statusCode): self {
		$this->statusCode = $statusCode;

		return $this;
	}

	/**
	 * Returns the value of a header field if present, or all header fields in the response if not specified
	 * @param string|null $name The optional header name
	 * @return array|string|null
	 */
	public function getHeader(?string $name = null) {
		if ($name) {
			$start = strtolower($name) . ': ';
			foreach ($this->headers as $header)
				if (strpos(strtolower($header), $start) === 0)
					return substr($header, strlen($start));
			return null;
		}

		return $this->headers;
	}

	/**
	 * Adds a new header field to the response, returning self
	 */
	public function addHeader(string $header): self {
		$this->headers[] = $header;

		return $this;
	}

	/**
	 * Returns the response's body
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * Sets the response's body
	 */
	public function setBody(string $body): self {
		$this->body = $body;
		
		return $this;
	}

	/**
	 * Outputs the response: header fields and the body
	 */
	public function send(): void {
		if ($this->statusCode != 200)
			http_response_code($this->statusCode);

		foreach ($this->headers as $header)
			header($header, false);

		echo $this->body;
	}

}