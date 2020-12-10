<?php
class HTTP {

	public static function request($method, $url, $header = [], $content = null, $options = []) {
		$options += [
				'method' => $method,
				'header' => implode("\r\n", $header),
				'content' => $content,
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
