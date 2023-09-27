<?php
namespace Subframe;

use Exception;

/**
 * Base MVC controller implementation
 * @package Subframe PHP Framework
 */
class Controller {

	/** @var Request */
	protected $request;

	public function __construct(Request $request) {
		$this->request = $request;
	}

	/**
	 * Outputs a view/template provided with given data
	 * @param string $__filename The filename of the view, without ".php" extension
	 * @param array|object $__data The data
	 * @param int $__status The optional HTTP status code
	 * @throws Exception
	 */
	protected function view(string $__filename, $__data = [], int $__status = 200): void {
		http_response_code($__status);
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
		extract((array) $__data);
		require "$__filename.php";
		error_reporting($error_reporting);
	}

	/**
	 * Processes a view/template, provided with data, and returns its output
	 * @param string $filename The filename of the view, without ".php" extension
	 * @param array $data The data
	 * @return string The output
	 * @throws Exception
	 */
	protected function obView(string $filename, array $data = []): string {
		ob_start();
		$this->view($filename, $data);
		return ob_get_clean();
	}

	/**
	 * Outputs JSON encoded object or array
	 * @param object|array $data The data to output
	 * @param int $status Optional HTTP status code
	 */
	protected function json($data = [], int $status = 200): void {
		http_response_code($status);
		header("Content-Type: application/json");
		die(json_encode($data));
	}

	/**
	 * Throws an exception; for convenience, as it can be part of an expression
	 * @param string $message The message of the exception
	 * @param int $code The code of the exception
	 * @throws Exception
	 */
	protected function throw(string $message, int $code = 500): void {
		throw new Exception($message, $code);
	}

	/**
	 * Redirects the request to another URL
	 * @param string $url The URL to go to
	 * @param int $code HTTP status code, such as 301; defaults to 302
	 */
	protected function redirect(string $url, int $code = 302): void {
		http_response_code($code);
		header("Location: " . str_replace("\n", "\0", $url));
		exit;
	}

}
