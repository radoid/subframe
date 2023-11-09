<?php
namespace Subframe;

/**
 * Middleware implementing content caching
 */
class CacheMiddleware implements MiddlewareInterface {

	/**
	 * Cache implementation
	 * @var CacheInterface
	 */
	protected $cache;

	/**
	 * Expiry time in seconds
	 */
	private int $ttl;

	/**
	 * URIs to include in the cache or exclude, as regular expressions
	 */
	private ?string $includeUri, $excludeUri;


	/**
	 * The constructor
	 */
	public function __construct(CacheInterface $cache, int $ttl = 86400, ?string $include = null, ?string $exclude = null) {
		$this->cache = $cache;
		$this->ttl = $ttl;
		$this->includeUri = $include;
		$this->excludeUri = $exclude;
	}

	/**
	 * Handles the request, caching the content, and finishing off with a 304 response quickly if the content was not modified
	 */
	public function process(RequestInterface $request, RequestHandlerInterface $next): ResponseInterface {
		$baseUri = '/' . trim(strtok($request->getUri(), '?'), '/');
		$isCachable = ($request->getMethod() == 'GET')
				&& (isset($this->includeUri) ?  preg_match("#$this->includeUri#", $baseUri) : true)
				&& (isset($this->excludeUri) ? !preg_match("#$this->excludeUri#", $baseUri) : true);
		$filename = 'response' . strtr($request->getUri(), '/?&.', '----');

		if ($isCachable) {
			if (($ifModifiedSince = $request->getHeader('If-Modified-Since')))
				if (($expiry = $this->cache->getExpiryTime($filename))
					&& $expiry - $this->ttl <= strtotime($ifModifiedSince))
				return new Response('', 304, ['Last-Modified' => date('r'), 'Vary' => 'Accept-Encoding']);
			
			if (($etag = $request->getHeader('If-None-Match')))
				if ($etag == $this->generateETag($filename, $this->cache->getExpiryTime($filename)))
					return new Response('', 304, ['ETag' => $etag, 'Vary' => 'Accept-Encoding']);

			if (($response = $this->cache->get($filename)))
				return $response;
		}

		$response = $next->handle($request);

		$etag = $this->generateETag($filename, time() + $this->ttl);
		$response = $response
				->withHeader('Last-Modified', date('r'))
				->withHeader('ETag', $etag)->withHeader('Vary', 'Accept-Encoding');

		if ($isCachable)
			$this->cache->set($filename, $response, $this->ttl);

		return $response;
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
