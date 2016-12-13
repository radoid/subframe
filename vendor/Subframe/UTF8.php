<?php
/**
 * Common string functions using UTF8 encoding
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class UTF8
{
	static function strtoupper($text) {
		return strtr(str_replace(
				array("\xC4\x8D", "\xC4\x87", "\xC5\xA1", "\xC4\x91", "\xC5\xBE"),
				array("\xC4\x8C", "\xC4\x86", "\xC5\xA0", "\xC4\x90", "\xC5\xBD"), $text),
			"abcdefghijklmnopqrstuvwxyz", "ABCDEFGHIJKLMNOPQRSTUVWXYZ");
	}

	static function strtolower($text) {
		return strtr(str_replace(
				array("\xC4\x8C", "\xC4\x86", "\xC5\xA0", "\xC4\x90", "\xC5\xBD"),
				array("\xC4\x8D", "\xC4\x87", "\xC5\xA1", "\xC4\x91", "\xC5\xBE"), $text),
			"ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz");
	}

	static function safe($key) {
		$key = str_replace(array("\xC4\x8C", "\xC4\x86", "\xC5\xA0", "\xC4\x90", "\xC5\xBD"), array("c", "c", "s", "dj", "z"), $key);
		$key = str_replace(array("\xC4\x8D", "\xC4\x87", "\xC5\xA1", "\xC4\x91", "\xC5\xBE"), array("c", "c", "s", "dj", "z"), $key);
		$key = str_replace(" ", "-", trim(preg_replace('/[^a-z0-9]+/', ' ', strtolower($key))));
		return (strlen($key) ? $key : "1");
	}

	static function limit($text, $maxlen = 45) {
		if (function_exists('mb_strlen'))
			return (mb_strlen($text, 'UTF-8') > $maxlen ? mb_substr($text, 0, $maxlen - 2, 'UTF-8') . "..." : $text);
		else
			return (strlen($text) > $maxlen ? substr($text, 0, $maxlen - 2) . "..." : $text);
	}

	static function plural($number, $singular, $dual = '', $plural = '') {
		if ($plural)
			return ($number % 100 > 10 && $number % 100 < 20 ? $plural : ($number % 10 == 1 ? $singular : ($number % 10 < 5 && $number % 10 > 0 ? $dual : $plural)));
		if ($dual)
			return ($number == 1 ? $singular : $dual);
		return ($number == 1 ? $singular : $singular . 's');
	}

}