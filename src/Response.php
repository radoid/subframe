<?php
namespace Subframe;

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
	public function __construct(int $status = 200, array $headers = [], string $body = '') {
		$this->status = $status;
		$this->headers = $headers;
		$this->body = $body;
	}

	/**
	 * Returns the response's status code
	 */
	public function getStatusCode(): int {
		return $this->status;
	}

	/**
	 * Returns the response's header fields
	 */
	public function getHeaders(): array {
		return $this->headers;
	}

	/**
	 * Returns the response's body
	 */
	public function getBody(): string {
		return $this->body;
	}

	/**
	 * Adds a new header field to the response
	 */
	public function addHeader(string $header): self {
		$this->headers[] = $header;
		return $this;
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

		return new Response($statusCode, [], $output);
	}

	/**
	 * Creates a JSON response from an array
	 */
	public static function fromData(array $data = [], int $statusCode = 200): Response {
		return new Response($statusCode, ['Content-Type: application/json; charset=utf-8'], json_encode($data));
	}

}