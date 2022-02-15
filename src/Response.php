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
	protected $status;

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
		$this->status = $statusCode;
		$this->body = $body;
		$this->headers = [];
		foreach ($headers as $name => $value)
			$this->headers[self::capitalizeName($name)] = $value;
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
		return $this->status;
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
		$name = self::capitalizeName($name);
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
		$headers = [self::capitalizeName($name) => $value] + $this->headers;
		return new self($this->body, $this->status, $headers);
	}

	/**
	 * Removes a header field, if present, from the response, returning a new instance
	 */
	public function withoutHeader(string $name): ResponseInterface {
		$headers = $this->getHeaders();
		unset($headers[self::capitalizeName($name)]);
		return new self($this->body, $this->status, $headers);
	}

	/**
	 * Outputs the response, both header fields and the body
	 */
	public function output(): void {
		http_response_code($this->status);
		foreach ($this->headers as $header)
			header($header);
		echo $this->body;
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
		return new Response(json_encode($data), $statusCode, ['Content-Type' => 'application/json; charset=utf-8']);
	}

	/**
	 * Capitalizes a header field name properly
	 */
	protected static function capitalizeName(string $name): string {
		return ucwords(strtolower($name), '-');
	}

}