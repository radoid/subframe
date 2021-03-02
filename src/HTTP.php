<?php
namespace Subframe;

/**
 * HTTP request functions
 * @package Subframe PHP Framework
 */
class HTTP {

	/**
	 * Makes an HTTP request
	 * @param string $method Request method to use
	 * @param string $url The URL
	 * @param string[] $header HTTP headers
	 * @param array|string $data The body
	 * @param array $files Optional files (filenames) to upload
	 * @param array $options Optional PHP stream context options
	 * @return array|bool An array with 'status', 'header' and 'content' keys, corresponding to the response, or false on error
	 * @throws \Exception
	 */
	static public function request(string $method, string $url, array $header = [], $data = [], array $files = [], array $options = []) {
		$body = (is_array($data) ? http_build_query($data) : $data);
		if ($method == 'POST')
			if ($files) {
				$boundary = '-------------'.uniqid();
				$body = self::buildMultipartQuery((array)$data, $files, $boundary);
				$header[] = 'Content-Type: multipart/form-data; boundary='.$boundary;
			} else
				$header[] = 'Content-Type: application/x-www-form-urlencoded';
		$header[] = 'Content-Length: '.strlen($body);

		$options = [
				'method' => $method,
				'header' => implode("\r\n", $header),
						'content' => $body,
		] + $options + [
				'timeout' => 20,
				'ignore_errors' => true,
		];

		$f = fopen($url, 'r', false, stream_context_create(['http' => $options]));
		if (!$f)
			return false;
		$meta = stream_get_meta_data($f);
		$body = stream_get_contents($f);
		fclose($f);

		$status = (int)trim(strstr($meta['wrapper_data'][0], ' '));
		$header = [];
		foreach ($meta['wrapper_data'] as $field)
			if ((@list($key, $value) = explode(': ', $field)) && $key && strlen($value))
				$header[$key] = (isset($header[$key]) ? array_merge((array)$header[$key], [$value]) : $value);

		return ['status' => $status, 'header' => $header, 'body' => $body];
	}

	/**
	 * Creates a multipart query body
	 * @param array|string $data
	 * @param array $files
	 * @param string $delimiter
	 * @return string
	 * @throws \Exception
	 */
	static public function buildMultipartQuery(array $data, array $files = [], string $delimiter) {
		$body = '';

		foreach ($data as $name => $value)
			$body .= "--".$delimiter."\r\n"
					.'Content-Disposition: form-data; name="'.$name."\"\r\n\r\n"
					.$value."\r\n";

		foreach ($files as $name => $filename) {
			$value = file_get_contents($filename);
			if ($value === false)
				throw new \Exception("Error reading $filename");
			$mime = 'application/octet-stream';
			if (function_exists('mime_content_type'))
				$mime = mime_content_type($filename);
			$body .= "--".$delimiter."\r\n"
					."Content-Disposition: form-data; name=\"$name\"; filename=\"$name\"\r\n"
					."Content-Type: $mime\r\n"
					."Content-Transfer-Encoding: binary\r\n\r\n"
					.$value."\r\n";
		}

		$body .= "--".$delimiter."--\r\n";

		return $body;
	}

}
