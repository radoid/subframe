<?php
namespace Subframe;

/**
 * Represents a response to an HTTP request
 */
class Response implements ResponseInterface {

	/**
	 * HTTP status code
	 * @var int
	 */
	protected $statusCode;

	/**
	 * HTTP header fields
	 * @var array
	 */
	protected $headers;

	/**
	 * The body of the response
	 * @var string
	 */
	protected $body;


	/**
	 * The constructor
	 */
	public function __construct(string $body, int $statusCode = 200, array $headers = []) {
		$this->statusCode = $statusCode;
		$this->body = $body;
		$this->headers = [];
		foreach ($headers as $name => $value)
			if (is_string($name))
				$this->headers[$this->capitalizeHeader($name)] = $value;
			else
				$this->headers[] = $value;
	}

	/**
	 * Creates a response from a view
	 */
	public static function fromView(string $filename, array $data = [], int $statusCode = 200): Response {
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
		ob_start();

		(function ($_filename, $_data) {
			extract($_data);
			require "$_filename.php";
		})($filename, $data);

		$output = ob_get_clean();
		error_reporting($error_reporting);

		return new Response($output, $statusCode, []);
	}

	/**
	 * Creates a JSON response from an array
	 */
	public static function fromData(array $data = [], int $statusCode = 200): Response {
		return new Response(json_encode($data, JSON_FORCE_OBJECT), $statusCode, ['Content-Type' => 'application/json; charset=utf-8']);
	}

	/**
	 * Constructs a Response with only "Location" header field
	 */
	public static function fromLocation(string $location, int $statusCode = 302): self {
		return new self('', $statusCode, ['Location' => $location]);
	}

	/**
	 * Returns the response's status code
	 */
	public function getStatusCode(): int {
		return $this->statusCode;
	}

	/**
	 * Returns all header fields in the response
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Returns the header field value, if present, or null otherwise
	 */
	public function getHeader($name): ?string {
		$name = $this->capitalizeHeader($name);
		return $this->headers[$name] ?? null;
	}

	/**
	 * Returns the response's body
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * Adds a new header field to the response, returning a new instance
	 */
	public function withHeader(string $name, string $value): ResponseInterface {
		$name = $this->capitalizeHeader($name);
		$headers = [$name => $value] + $this->headers;
		return new self($this->body, $this->statusCode, $headers);
	}

	/**
	 * Removes a header field, if present, from the response, returning a new instance
	 */
	public function withoutHeader(string $name): ResponseInterface {
		$name = $this->capitalizeHeader($name);
		$headers = $this->getHeaders();
		unset($headers[$name]);
		return new self($this->body, $this->statusCode, $headers);
	}

	/**
	 * Outputs the response, both header fields and the body
	 */
	public function send(): void {
		http_response_code($this->statusCode);

		if (!headers_sent())
			foreach ($this->headers as $name => $value)
				header(is_int($name) ? $value : "$name: $value", false);

		echo $this->body;

		if (function_exists('fastcgi_finish_request'))
			fastcgi_finish_request();
		elseif (function_exists('litespeed_finish_request'))
			litespeed_finish_request();
		else
			while(ob_get_level())
				ob_end_flush();
	}

	/**
	 * Capitalizes a header field name properly
	 */
	private function capitalizeHeader(string $name): string {
		$name = ucwords(strtolower($name), '-');
		return ($name == 'Etag' ? 'ETag' : $name);
	}

}