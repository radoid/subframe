<?php
namespace Subframe;

use Exception;

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
		http_response_code($__status);
		$error_reporting = error_reporting(error_reporting() & ~E_NOTICE & ~E_WARNING);
		extract($__data);
		require "$__filename.php";
		error_reporting($error_reporting);
	}

	/**
	 * Outputs JSON encoded object or array
	 * @param array $data The data to output
	 * @param int $status Optional HTTP status code
	 */
	protected function json(array $data = [], int $status = 200): void {
		http_response_code($status);
		header('Content-Type: application/json');
		die(json_encode($data));
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

	/**
	 * Sets the ETag header and triggers the 304 response if it matches to the requested ETag
	 * @param string $etag The ETag value
	 */
	protected static function setETag(string $etag): void {
		if (($before = $_SERVER['HTTP_IF_NONE_MATCH'] ?? ''))
			if ($before == $etag) {
				header_remove();
				http_response_code(304); // 304 Not Modified
				exit;
			}
		
		header("ETag: $etag");
	}

	/**
	 * Sets the Last-Modified header and triggers the 304 response if timestamp not newer then requested
	 * @param int $timestamp The Unix timestamp
	 */
	protected static function setLastModified(int $timestamp): void {
		if (($before = $_SERVER['HTTP_IF_MODIFIED_SINCE']))
			if (strtotime($before) >= $timestamp) {
				header_remove();
				http_response_code(304); // 304 Not Modified
				exit;
			}
		
		header('Last-Modified: '.date('r', $timestamp));
	}

	/**
	 * Tells whether the request is intended for JSON response
	 */
	protected function acceptsJson(): bool {
		return strpos($_SERVER['HTTP_ACCEPT'] ?? '', '/json') !== false;
	}

	/**
	 * Obtains uploaded files array in a normalized form; empty files are not present in the resulting array
	 * @throws Exception in case of an upload error
	 */
	protected function getFiles(): array {
		$upload_max_filesize = ini_get('upload_max_filesize');
		$post_max_size = ini_get('post_max_size');

		if ($_SERVER['REQUEST_METHOD'] == 'POST' && ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && !$_POST && !$_FILES)
			throw new Exception("Total upload size exceeds limit ($post_max_size).", 400);

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

		foreach ($files as $file)
			foreach ((key_exists('name', $file) ? [$file] : $file) as $file)
				if ($file['error'])
					switch ($file['error']) {
						case UPLOAD_ERR_INI_SIZE:
							throw new Exception("File \"$file[name]\" exceeds size limit ($upload_max_filesize).", 422);
						default:
							throw new Exception("Unexpected error #$file[error] during upload.", 500);
					}
		
		return $files;
	}

}
