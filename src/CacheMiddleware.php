<?php
namespace Subframe;

/**
 * Middleware implementing content caching
 */
class CacheMiddleware implements MiddlewareInterface {

	/**
	 * Default TTL
	 */
	protected const TTL = 24*3600;

	/**
	 * Cache implementation
	 * @var CacheInterface
	 */
	protected $cache;


	/**
	 * The constructor
	 */
	public function __construct(CacheInterface $cache) {
		$this->cache = $cache;
	}

	/**
	 * Handles the request, caching the content, and finishing off with a 304 response quickly if the content was not modified
	 */
	public function process(RequestInterface $request, RequestHandlerInterface $next): ResponseInterface {
		$isCachable = ($request->getMethod() == 'GET');
		if ($isCachable) {
			$name = 'content'.$request->getUri().($request->acceptsJson() ? '.json':'.html');

			$ifModifiedSince = strtotime($request->getHeader('If-Modified-Since') ?? '');
			if ($ifModifiedSince
					&& ($timestamp = $this->cache->getExpiryTime($name))
					&& $timestamp - self::TTL <= $ifModifiedSince)
				return new Response('', 304);
			// TODO Generating a 304 response MUST generate any of the following header fields that would have been sent in a 200 (OK) response to the same request: Cache-Control, Content-Location, Date, ETag, Expires, and Vary.

			if (($content = $this->cache->get($name)))
				return new Response($content, 200, ['Last-Modified: '.date('r')]);
		}

		$response = $next->handle($request);

		if ($isCachable) {
			$this->cache->set($name, $response->getBody(), self::TTL);
			$response->withHeader('Last-Modified', date('r'));
		}

		return $response;
	}

}
