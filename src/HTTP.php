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
	 * @param string|null $content The body of the request
	 * @param array $options Optional stream context options
	 * @return array|bool An array with 'status', 'header' and 'content' of the response, or false
	 */
	static public function request(string $method, string $url, array $header = [], string $content = null, array $options = []) {
		$options = [
				'method' => $method,
				'header' => implode("\r\n", $header),
				'content' => $content,
		] + $options + [
				'timeout' => 20,
				'follow_location' => 0,
				'ignore_errors' => true,
		];

		$f = fopen($url, 'r', false, stream_context_create(['http' => $options]));
		if (!$f)
			return false;
		$meta = stream_get_meta_data($f);
		$content = stream_get_contents($f);
		fclose($f);

		$status = (int)trim(strstr($meta['wrapper_data'][0], ' '));
		$header = [];
		foreach ($meta['wrapper_data'] as $field)
			if ((@list($key, $value) = explode(': ', $field)) && $key && strlen($value))
				$header[$key] = (isset($header[$key]) ? array_merge((array)$header[$key], [$value]) : $value);

		return ['status' => $status, 'header' => $header, 'content' => $content];
	}

}
