<?php
namespace Subframe;

class Response implements ResponseInterface {

	/** @var int */
	private $status;

	/** @var array */
	private $headers;

	/** @var string */
	private $body;

	public function __construct(int $status = 200, array $headers = [], string $body = '') {
		$this->status = $status;
		$this->headers = $headers;
		$this->body = $body;
	}

	public function getStatusCode(): int {
		return $this->status;
	}

	public function getHeaders(): array {
		return $this->headers;
	}

	public function getBody(): string {
		return $this->body;
	}

	public function output(): void {
		http_response_code($this->status);
		echo $this->body;
	}

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

	public static function fromData(array $data = [], int $statusCode = 200): Response {
		return new Response($statusCode, ['Content-Type: application/json; charset=utf-8'], json_encode($data));
	}

}