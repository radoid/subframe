<?php
namespace Subframe;

use Exception;
use Throwable;

/**
 * Base MVC controller implementation
 * @package Subframe PHP Framework
 */
class Controller {

	/**
	 * Outputs a view/template provided with given data
	 * @param string $__filename The filename of the view, without ".php" extension
	 * @param array $__data The data
	 * @param int $__status The optional HTTP status code
	 * @throws Exception
	 */
	protected function view(string $__filename, array $__data = [], int $__status = 200): void {
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);

		http_response_code($__status);
		extract($__data);
		require "$__filename.php";

		error_reporting($error_reporting);
	}

	/**
	 * Renders a view/template into a string, using given data
	 * @param string $filename The filename of the view, without ".php" extension
	 * @param array $data The data
	 * @throws Throwable
	 */
	public static function getView(string $filename, array $data = []): string {
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
		ob_start();

		try {
			(function ($__filename, $__data) {
				extract($__data);
				require "$__filename.php";
			})($filename, $data);
		} catch (Throwable $exception) {}

		$output = ob_get_clean();
		error_reporting($error_reporting);

		if (isset($exception))
			throw $exception;
		
		return $output;
	}

	/**
	 * Outputs JSON encoded object or array
	 * @param array $data The data to output
	 * @param int $status Optional HTTP status code
	 */
	protected function json(array $data = [], int $status = 200): void {
		$json = json_encode((object)$data);
		http_response_code($status);
		header('Content-Type: application/json');
		header('Content-Length: '.strlen($json));
		echo $json;
	}

	/**
	 * Flushes the response to the client, e.g. to allow a long processing task to continue
	 */
	protected function finish() {
		while (ob_get_level())
			ob_end_flush();
		flush();
		ignore_user_abort(true);
		set_time_limit(0);
		if (function_exists('fastcgi_finish_request')) {
			session_write_close();
			fastcgi_finish_request();
		}
	}

	/**
	 * Redirects the request to another URL
	 * @param string $url The URL to go to
	 * @param int $code HTTP status code, such as 301; defaults to 302
	 */
	protected function redirect(string $url, int $code = 302): void {
		http_response_code($code);
		header('Location: ' . str_replace("\n", "\0", $url));
		exit;
	}

}
