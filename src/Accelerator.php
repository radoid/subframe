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
	 * Paths to include in the cache or exclude, as regular expressions
	 */
	private string $includePath, $excludePath;


	/**
	 * The constructor
	 */
	public function __construct(Cache $cache, ?string $include = null, ?string $exclude = null) {
		$this->cache = $cache;
		$this->includePath = $include;
		$this->excludePath = $exclude;
	}

	/**
	 * Handles the request represented by the global constants REQUEST_METHOD and REQUEST_URI or REDIRECT_URL
	 */
	public function handleGlobalRequestUri(callable $next): void {
		$request = Request::fromGlobalRequestUri();

		$this->handle($request, $next);
	}

	/**
	 * Handles the given request. If the response is already in the cache, it is served. Otherwise, a callable is called
	 * that should output a response and set response headers, typically using a router to dispatch the request.
	 */
	public function handle(Request $request, callable $next): void {
		$path = $request->getPath();
		$isCachable = ($request->getMethod() == 'GET')
				&& (isset($this->includePath) ?  preg_match("#$this->includePath#", $path) : true)
				&& (isset($this->excludePath) ? !preg_match("#$this->excludePath#", $path) : true);
		$acceptsGzip = (strpos($request->getHeader('Accept-Encoding') ?? '', 'gzip') !== false && extension_loaded('zlib'));
		$filename = 'output' . strtr($request->getPathAndQueryString(), '/?&.', '----') . '.html' . ($acceptsGzip ? '.gz' : '');
		$timestamp = time();

		if ($isCachable) {
			if (($before = $request->getHeader('If-None-Match')))
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
			$result = $next($request);
		} catch (Throwable $e) {
			$result = $e;
		}
		$output = ob_get_flush();
		$headers = headers_list();

		if ($result instanceof Throwable)
			throw $result;
		
		$isText = array_reduce($headers, fn ($isText, $header) => $isText || stripos($header, 'Content-Type: text/') === 0, false);
		if ($isCachable && $isText && strlen($output))
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
