<?php
namespace Subframe;

interface RequestHandlerInterface {

	public function handle(RequestInterface $request): ResponseInterface;

}
