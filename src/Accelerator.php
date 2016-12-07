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
	private $cache;

	/**
	 * Paths to include in the cache or exclude, as regular expressions
	 */
	private $includePath, $excludePath;


	/**
	 * The constructor
	 */
	public function __construct(Cache $cache, ?string $include = null, ?string $exclude = null) {
		$this->cache = $cache;
		$this->includePath = $include;
		$this->excludePath = $exclude;
	}

	/**
	 * Handles the request represented by the global REQUEST_METHOD and REQUEST_URI constants
	 */
	public function handleGlobalRequestUri(Closure $next): void {
		$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
		$path = $_SERVER['REDIRECT_URL'] ?? rawurldecode(strtok($_SERVER['REQUEST_URI'], '?'));

		$this->handle($method, $path, getallheaders(), $next);
	}

	/**
	 * Handles the given request. If the response is already in the cache, it is served. Otherwise, a closure is called
	 * that should generate a response, typically using a router to dispatch the request.
	 */
	public function handle(string $method, string $path, array $headers, Closure $next): void {
		$basePath = strtok($path, '?');
		$isCachable = ($method == 'GET')
				&& (isset($this->includePath) ?  preg_match("#$this->includePath#", $basePath) : true)
				&& (isset($this->excludePath) ? !preg_match("#$this->excludePath#", $basePath) : true);
		$acceptsGzip = (strpos($headers['Accept-Encoding'] ?? '', 'gzip') !== false && extension_loaded('zlib'));
		$filename = 'output' . strtr($path, '/?&', '---') . '.html' . ($acceptsGzip ? '.gz' : '');
		$timestamp = time();

		if ($isCachable) {
			if (($before = $headers['If-None-Match'] ?? null))
				if ($before == $this->generateETag($filename, $this->cache->getExpiryTime($filename))) {
					http_response_code(304); // 304 Not Modified
					exit;
				}

			if (($content = $this->cache->get($filename))) {
				header('ETag: ' . $this->generateETag($filename, $this->cache->getExpiryTime($filename)));
				header('Vary: Accept-Encoding');
				if ($acceptsGzip) {
					ini_set('zlib.output_compression', false);
					header('Content-Encoding: gzip');
				}
				echo $content;
				exit;
			}

			header('ETag: ' . $this->generateETag($filename, $timestamp + $this->cache->getLifetime()));
			header('Vary: Accept-Encoding');
		}

		ob_start();
		try {
			$result = $next($method, $path, $headers);
		} catch (Throwable $e) {
			$result = $e;
		}
		$output = ob_get_flush();
		$headers = headers_list();

		if ($result instanceof Throwable)
			throw $result;
		
		$isHtml = array_reduce($headers, function ($isHtml, $header) { return $isHtml || stripos($header, 'Content-Type: text/html') === 0; }, false);
		if ($isCachable && $isHtml && strlen($output))
			$this->cache->set($filename, $acceptsGzip ? gzencode($output) : $output);
	}

	/**
	 * Generates ETag for a specific file in the cache with an expiry timestamp
	 * @param string $filename 
	 * @param int $timestamp 
	 * @return string 
	 */
	protected function generateETag(string $filename, int $timestamp): string {
		return md5($filename . $timestamp);
	}

}
