<?php
/**
 * Filesystem functions
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class File
{
	static function unique($destination, $mkdir = true) {
		$parts = pathinfo($destination);
		if ($mkdir)
			@mkdir($parts['dirname'], 0777, true) and @chmod($parts['dirname'], 0777);
		$parts['basename'] = preg_replace('/\..*/', '', $parts['basename']);
		$parts['basename'] = self::safe($parts['basename']);
		$parts['extension'] = self::safe($parts['extension']);
		$i = "";
		if (preg_match('~^(.*)(-\d+)$~', $parts['basename'], $matches)) {
			$parts['basename'] = $matches[1];
			$i = $matches[2];
		}
		while (file_exists("$parts[dirname]/$parts[basename]$i.$parts[extension]"))
			$i--;
		return "$parts[dirname]/$parts[basename]$i.$parts[extension]";
	}

	static function safe($name) {
		// Pretvorba iz UTF-a u ASCII, mala pa velika slova
		$name = str_replace(array("\xC4\x8C", "\xC4\x86", "\xC5\xA0", "\xC4\x90", "\xC5\xBD"), array("c", "c", "s", "dj", "z"), $name);
		$name = str_replace(array("\xC4\x8D", "\xC4\x87", "\xC5\xA1", "\xC4\x91", "\xC5\xBE"), array("c", "c", "s", "dj", "z"), $name);
		// Pretvorba iz ISO-8859-2 u ASCII, mala pa velika slova
		//$key = str_replace (array ("\xE8", "\xE6", "\xB9", "\xBE", "\xF0", "\xC8", "\xC6", "\xA9", "\xAE", "\xD0"), array ("c", "c", "s", "z", "dj", "c", "c", "s", "z", "dj"), $key);
		$name = str_replace(" ", "-", trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($name))));
		return (strlen($name) ? $name : "1");
	}

}