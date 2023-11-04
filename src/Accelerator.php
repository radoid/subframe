<?php
namespace Subframe;

use Closure;
use Throwable;

/**
 * Implements a simple HTTP cache based on file and opcode caching
 * @package Subframe
 */
class Accelerator {

	/**
	 * Represents the cache
	 */
	private Cache $cache;

	/**
	 * Expiry time in seconds
	 */
	private int $ttl;

	/**
	 * URIs to include in the cache or exclude, as regular expressions
	 */
	private string $includeUri, $excludeUri;


	/**
	 * The constructor
	 */
	public function __construct(Cache $cache, int $ttl = 86400, ?string $include = null, ?string $exclude = null) {
		$this->cache = $cache;
		$this->ttl = $ttl;
		$this->includeUri = $include;
		$this->excludeUri = $exclude;
	}

	/**
	 * Handles the request represented by the global REQUEST_METHOD and REQUEST_URI constants
	 */
	public function handleRequestUri(Closure $next): void {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$uri = rawurldecode(strtok($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'], '?'));

		$this->handle($method, $uri, getallheaders(), $next);
	}

	/**
	 * Handles the request represented by the global REQUEST_METHOD and PATH_INFO constants
	 */
	public function handlePathInfo(Closure $next): void {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$uri = rawurldecode($_SERVER['ORIG_PATH_INFO'] ?? $_SERVER['PATH_INFO'] ?? '/')
			. ($_SERVER['QUERY_STRING'] !== '' ? '?'.$_SERVER['QUERY_STRING'] : '');

		$this->handle($method, $uri, getallheaders(), $next);
	}

	/**
	 * Handles the given request. If the response is already in the cache, it is served. Otherwise, a closure is called
	 * that should generate a response, typically using a router to dispatch the request.
	 */
	public function handle(string $method, string $uri, array $headers, Closure $next): void {
		$baseUri = strtok($uri, '?');
		$isCachable = ($method == 'GET')
				&& (isset($this->includeUri) ?  preg_match("#$this->includeUri#", $baseUri) : true)
				&& (isset($this->excludeUri) ? !preg_match("#$this->excludeUri#", $baseUri) : true);
		$isGzippable = (strpos($headers['Accept-Encoding'] ?? '', 'gzip') !== false && extension_loaded('zlib'));
		$filename = 'output' . strtr($uri, '/?&', '---') . '.html' . ($isGzippable ? '.gz' : '');
		$timestamp = time();

		if ($isCachable) {
			if (($before = $headers['If-None-Match'] ?? null))
				if ($before == $this->generateETag($filename, $this->cache->getTime($filename))) {
					http_response_code(304); // 304 Not Modified
					exit;
				}

			if ($this->cache->has($filename)) {
				header('ETag: '.$this->generateETag($filename, $this->cache->getTime($filename)));
				header('Vary: Accept-Encoding');
				if ($isGzippable) {
					ini_set('zlib.output_compression', false);
					header('Content-Encoding: gzip');
				}
				$this->cache->dump($filename);
				exit;
			}

			header('ETag: '.$this->generateETag($filename, $timestamp + $this->ttl));
			header('Vary: Accept-Encoding');
		}

		ob_start();
		try {
			$result = $next($method, $uri, $headers);
		} catch (Throwable $e) {
			$result = $e;
		}
		$output = ob_get_flush();
		$headers = headers_list();

		if ($result instanceof Throwable)
			throw $result;
		
		$isHtml = array_reduce($headers, fn ($isHtml, $header) => $isHtml || stripos($header, 'Content-Type: text/html') === 0, false);
		if ($isCachable && $isHtml && strlen($output))
			$this->cache->put($filename, $isGzippable ? gzencode($output) : $output, $this->ttl);
	}

	/**
	 * Generates ETag for a specific file in the cache with an expiry timestamp
	 * @param string $filename 
	 * @param int $timestamp 
	 * @return string 
	 */
	protected function generateETag(string $filename, int $timestamp): string {
		return md5($filename.$timestamp);
	}

}
