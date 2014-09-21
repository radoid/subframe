<?php
/**
 * Example for a controller presenting errors (typically specified in .htaccess files)
 *
 * @package: Subframe PHP Framework
 */
class Errors extends Subframe\Controller
{
	function index () {
		$status = @$_SERVER['REDIRECT_STATUS']
			or $status = 200;
		$error = strtr ($status, array (400 => "Bad request", 401 => "Unauthorized", 403 => "Forbidden", 404 => "Not Found", 500 => "Server Error"))
			or $error = "Unexpected Error.";

		$this->view('error', array('error' => $error), $status);
	}

}