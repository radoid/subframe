<?php
/**
 * Example for a homepage controller
 *
 * @package: Subframe PHP Framework
 */
class Home extends Subframe\Controller
{
	function index() {
		$this->view('home');
	}

}