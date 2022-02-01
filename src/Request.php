<?php
namespace Subframe;

use Exception;

class Request {

	/**
	 * Obtains the current request's method
	 * @return string
	 */
	public static function getMethod(): string {
		return $_SERVER['REQUEST_METHOD'] ?? 'GET';
	}

	/**
	 * Obtains the current request's URI; in case of a shell script, it's built from the script's arguments
	 * @return string
	 */
	public static function getUri(): string {
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
	public static function getRelativeUri(): string {
		$uri = self::getUri();
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
	 * Remote address the request was made from
	 * @return string
	 */
	public static function getRemoteAddr(): string {
		if (!empty ($_SERVER['HTTP_X_FORWARDED_FOR']))
			foreach (explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']) as $ipaddr)
				if ((int)$ipaddr != 10 && (int)$ipaddr != 192 && (int)$ipaddr != 127)
					return $ipaddr;
		return $_SERVER['REMOTE_ADDR'];
	}

	/**
	 * Obtains uploaded files array in a normalized form
	 * @return array
	 * @throws Exception
	 */
	public static function getUploadedFiles(): array {
		$files = [];
		foreach ($_FILES ?? [] as $varname => $file)
			if (is_array($file['name'])) {
				foreach ($file as $key => $array)
					foreach ($array as $i => $value)
						if ($file['error'][$i] != UPLOAD_ERR_NO_FILE)
							$files[$varname][$i][$key] = $value;
			} else
				if ($file['error'] != UPLOAD_ERR_NO_FILE)
					$files[$varname][] = $file;

		if ($_SERVER['REQUEST_METHOD'] == 'POST') {
			$upload_max_filesize = intval(ini_get('upload_max_filesize'));
			$post_max_size = intval(ini_get('post_max_size'));

			if ($_SERVER['CONTENT_LENGTH'] > 0 && !$_POST && !$_FILES)
				throw new Exception("Total upload size exceeds limit ($post_max_size MB).", 400);

			foreach ($files as $file)
				if ($file['error'])
					switch ($file['error']) {
						case UPLOAD_ERR_INI_SIZE:
							throw new Exception("\"$file[name]\" exceeds size limit ($upload_max_filesize MB).", 400);
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

		return $files;
	}

	/**
	 * Tells whether the request was made with XMLHttpRequest (an AJAX request)
	 * @return boolean
	 */
	public static function isAjax(): bool {
		return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') == 'XMLHttpRequest';
	}

}