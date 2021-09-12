<?php
namespace Subframe;

/**
 * Implements a simple HTTP cache
 * @package Subframe
 */
class Accelerator {

	/**
	 * Represents the cache
	 */
	private FileCache $cache;

	/**
	 * Paths to include in the cache or exclude, as regular expressions
	 */
	private ?string $includePath, $excludePath;


	/**
	 * The constructor
	 */
	public function __construct(FileCache $cache, ?string $include = null, ?string $exclude = null) {
		$this->cache = $cache;
		$this->includePath = $include;
		$this->excludePath = $exclude;
	}

	/**
	 * Handles the given request. If the response is already in the cache, it is served. Otherwise, a callable is called
	 * that should output a response and set response headers, typically using a router to dispatch the request.
	 */
	public function handle(Request $request, callable $next): ?Response {
		$path = $request->getPath();
		$isCachable = ($request->getMethod() == 'GET')
				&& (isset($this->includePath) ?  preg_match("#$this->includePath#", $path) : true)
				&& (isset($this->excludePath) ? !preg_match("#$this->excludePath#", $path) : true);
		$acceptsGzip = (strpos($request->getHeader('Accept-Encoding') ?? '', 'gzip') !== false && extension_loaded('zlib'));
		$filename = 'response' . strtr($request->getPathAndQueryString(), '/?&.', '----') . ($acceptsGzip ? '-gz' : '');

		if ($isCachable) {
			if (($content = $this->cache->get($filename)))
				$content = unserialize($content);

			if ($content instanceof Response) {
				if (($before = $request->getHeader('If-None-Match')))
					if ($content->getHeader('ETag') == $before)
						return new Response('', 304);

				return $content;
			}
		}

		/** @var ?Response */
		$response = $next($request);

		if ($isCachable && $response) {
			$isCompressible = !$response->getHeader('Content-Encoding')
					&& strpos($response->getHeader('Content-Type') ?? 'text/html', 'text/') === 0;
			if ($isCompressible && $acceptsGzip) {
				$body = gzencode($response->getBody());
				$response->setBody($body);
				$response->addHeader('Content-Encoding: gzip');
				$response->addHeader('Content-Length: ' . strlen($body));
			}
			$response->addHeader('ETag: ' . uniqid());
			$this->cache->set($filename, serialize($response));
		}
		
		return $response;
	}

}
