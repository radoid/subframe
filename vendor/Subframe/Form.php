<?php
/**
 * Common functionality for web forms
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Form
{
	function __construct(array $layout) {
		foreach ($layout as $key => $value)
			$this->$key = $value;
	}

	/**
	 * Filters all variables in a user-input object or array (such as $_POST) using user-defined functions
	 * @param array $data the array to be processed
	 * @return array the processed copy of the array
	 */
	function input($data) {
		foreach ($this as $key => $attributes) {
			if (is_object($data))
				$value = & $data->$key;
			else
				$value = & $data[$key];
			if ($attributes instanceof self) {
				if ($value)
					foreach ($value as &$row)
						$row = $this->$key->input($row);
			} elseif (($filter = @$attributes['input']) && (is_callable($filter) || is_callable($filter = array(__CLASS__, $filter)))) {
				if (is_array($value)) {
					//foreach ($value as &$element)
					//	if ($element !== "")
					//		$element = call_user_func($filter, $element);
					$value = call_user_func($filter, $value);
				} else if ($value !== "")
					$value = call_user_func($filter, $value);
			}
		}
		return $data;
	}

	/**
	 * Filters all variables in an array or object intended for output, using user-defined functions
	 * @param array $data the array to be processed
	 * @return array the processed copy of the array
	 */
	function output($data) {
		foreach ($this as $key => $attributes) {
			if (is_object($data))
				$value = & $data->$key;
			else
				$value = & $data[$key];
			if ($attributes instanceof self) {
				if ($value)
					foreach ($value as &$row)
						$row = $this->$key->output($row);
			//} elseif (($filter = @$attributes['output']) && (is_callable($filter) || is_callable($filter = array(__CLASS__, $filter)))) {
			} elseif (($filter = @$attributes['output']) && (is_callable($filter) || method_exists(__CLASS__, $filter) && ($filter = array(__CLASS__, $filter)))) {
				if (is_array($value)) {
					//foreach ($value as &$element)
					//	if ($element !== "")
					//		$element = call_user_func($filter, $element);
					$value = call_user_func($filter, $value);
				} else if ($value !== "")
					$value = call_user_func($filter, $value);
			}
		}
		return $data;
	}

	/**
	 * Trims all variables in an array (such as $_POST)
	 * @param array $data the array to be trimmed
	 * @return array trimmed result
	 */
	static function trim(array $data) {
		foreach ($data as $key => &$value)
			if (is_string($value))
				$data[$key] = trim($value);
			elseif (is_array($value))
				for ($i = 0; $i < count($value); $i++)
					if (is_array($value[$i]) && !strlen(trim(implode("", $value[$i]))))
						array_splice($value, $i--, 1);
		return $data;
	}

	/**
	 * HTML-encodes all variables in an array (such as $_POST)
	 * @param array $data the array to be encoded
	 * @return array encoded result
	 */
	static function htmlspecialchars(array $data) {
		return array_map(create_function('$value', 'return (is_array($value) ? Form::htmlspecialchars($value) : (is_string($value) ? htmlspecialchars($value) : $value));'), $data);
	}

	/**
	 * Converts number from ASCII into format D.MM.YYYY.
	 * @param string $date date in ISO format
	 * @return null|string date in format or NULL if impossible
	 */
	static function number2hr($number, $decimals = -1) {
		if (is_numeric($number)) {
			$hr = str_replace("-", "–", number_format($number, $decimals >= 0 ? $decimals : 2, ",", "."));
			if ($decimals == -1)
				$hr = str_replace(",00", "", $hr);
			if (preg_match("/^[^ ]*( .+)$/", $number, $matches))
				$hr .= $matches[1];
			return $hr;
		}
		return $number;
	}

	static function hr2number($number) {
		return str_replace(",", ".", str_replace(".", "", str_replace("–", "-", $number)));
	}

	/**
	 * Converts date from ISO format YYYY-MM-DD into format DD.MM.YYYY
	 * @param string $date date in ISO format
	 * @return false|string date in format or FALSE if impossible
	 */
	static function date2de($date) {
		if (preg_match('/^(\d+)-(\d+)-(\d+)$/', trim($date), $m) && checkdate($m[2], $m[3], $m[1]))
			return sprintf("%02d.%02d.%04d", $m[3], $m[2], $m[1]);
		return false;
	}

	static function de2date($date) {
		if (preg_match('/^(\d+)\.(\d+)\.(\d+)?\.?$/', trim($date), $m) && checkdate($m[2], $m[1], $m[3]))
			return sprintf("%04d-%02d-%02d", (@$m[3] ? ($m[3] < 100 ? 1900+$m[3] : $m[3]) : date("Y")), $m[2], $m[1]);
		return false;
	}

	/**
	 * Converts date from ISO format YYYY-MM-DD into format D.MM.YYYY.
	 * @param string $date date in ISO format
	 * @return false|string date in format or FALSE if impossible
	 */
	static function date2hr($date) {
		if (preg_match('/^(\d+)-(\d+)-(\d+)$/', trim($date), $m) && checkdate($m[2], $m[3], $m[1]))
			return sprintf("%d.%02d.%04d.", $m[3], $m[2], $m[1]);
		return false;
	}

	static function hr2date($date) {
		if (preg_match('/^(\d+)\.(\d+)\.(\d+)?\.?$/', trim($date), $m) && checkdate($m[2], $m[1], $m[3]))
			return sprintf("%04d-%02d-%02d", (@$m[3] ? ($m[3] < 100 ? 1900+$m[3] : $m[3]) : date("Y")), $m[2], $m[1]);
		return false;
	}

	/**
	 * Converts date from ISO format YYYY-MM-DD into English format MM/DD/YYYY
	 * @param string $date date in ISO format
	 * @return false|string date in English format or FALSE if impossible
	 */
	static function date2en($date) {
		if (preg_match('/^(\d+)-(\d+)-(\d+)$/', trim($date), $m) && checkdate($m[2], $m[3], $m[1]))
			return sprintf("%02d/%02d/%04d", $m[3], $m[2], $m[1]);
		return false;
	}

	static function en2date($date) {
		if (preg_match('~^(\d+)[/.](\d+)[/.]?(\d*)$~', trim($date), $m) && checkdate($m[2], $m[1], $m[3]))
			return sprintf('%04d-%02d-%02d', ($m[3] ? ($m[3] < 100 ? 1900+$m[3] : $m[3]) : date("Y")), $m[2], $m[1]);
		return false;
	}

	static function time2en($time) {
		if (preg_match('/^(\d+):(\d+):?(\d*)$/', trim($time), $m) && $m[1] >= 0 && $m[1] <= 24 && ($m[2] === "" || $m[2] >= 0 && $m[2] < 60))
			return sprintf("%02d:%02d %s", $m[1] == 0 ? 12 : $m[1] % 12, $m[2], $m[1] == 0 || $m[1] > 12 ? "pm" : "am");
		return false;
	}

	static function en2time($time) {
		if (preg_match('~^(\d+):?(\d*) ?(a|p|)m?$~i', trim($time), $m) && $m[1] >= 0 && $m[1] <= 24 && ($m[2] === "" || $m[2] >= 0 && $m[2] < 60))
			return sprintf('%02d:%02d:00', ($m[1] + ($m[3] == 'p' || $m[3] == 'P' ? 12 : 0)) % 24, +$m[2]);
		return false;
	}

	static function hidden($name, $value = "", $attributes = array()) {
		echo "<input id=\"" . (isset ($attributes['id']) ? $attributes['id'] : "input-$name") . "\" type=\"hidden\" name=\"$name\" value=\"" . htmlspecialchars($value) . "\" " . self::attributes($attributes) . " />\n";
	}

	static function text($name, $value = "", $attributes = array()) {
		echo "<input id=\"" . (isset ($attributes['id']) ? $attributes['id'] : "input-$name") . "\" type=\"text\" name=\"$name\" value=\"" . htmlspecialchars($value) . "\" " . self::attributes($attributes) . " />\n";
	}

	static function email($name, $value = "", $attributes = array()) {
		self::text($name, $value, $attributes);
	}

	static function password($name, $value = "", $attributes = array()) {
		echo "<input id=\"" . (isset ($attributes['id']) ? $attributes['id'] : "input-$name") . "\" type=\"password\" name=\"$name\" value=\"" . htmlspecialchars($value) . "\" " . self::attributes($attributes) . " />\n";
	}

	static function textarea($name, $value, $attributes = array()) {
		echo "<textarea id=\"" . (isset ($attributes['id']) ? $attributes['id'] : "input-$name") . "\" name=\"$name\" " . self::attributes($attributes) . ">" . htmlspecialchars($value) . "</textarea>\n";
	}

	static function checkbox($name, $value, $options, $attributes = array()) {
		$options = (is_array($options) ? $options : array('1' => $options));
		$multiple = (count($options) > 1 ? '[]' : '');
		foreach ($options as $key => $option)
			echo "<label><input type=\"checkbox\" name=\"$name$multiple\" id=\"input-$name-$key\" value=\"" . htmlspecialchars($key) . "\" " . ((is_array($value) && in_array($key, $value)) || (!is_array($value) && $value == $key) || ($multiple && is_numeric($value) && is_numeric($key) && ($value & $key)) ? "checked" : "") . self::attributes($attributes) . ">&nbsp;" . htmlspecialchars($option) . "</label>\n";
	}

	static function radio($name, $value, $options, $attributes = array()) {
		foreach ($options as $key => $option)
			echo "<label><input type=\"radio\" name=\"$name\" id=\"input-$name-$key\" value=\"$key\" " . ($key == $value ? "checked" : "") . self::attributes($attributes) . ">&nbsp;" . htmlspecialchars($option) . "</label>\n";
	}

	static function select($name, $value, $options, $attributes = array()) {
		echo "<select id=\"input-$name\" name=\"$name\"" . self::attributes($attributes) . ">\n";
		foreach ($options as $key => $option)
			echo "\t<option value=\"$key\" " . ($key == $value ? "selected" : "") . ">" . htmlspecialchars($option) . "</option>\n";
		echo "</select>\n";
	}

	static function file($name, $value, $attributes = array()) {
		echo "<input type=\"hidden\" name=\"$name\" id=\"input-$name\" value=\"" . htmlspecialchars($value) . "\" />\n";
		echo "<input type=\"file\" name=\"$name\" id=\"upload-$name\" " . self::attributes($attributes) . "/>\n";
	}

	static function attributes(array $attributes) {
		$html = "";
		foreach ($attributes as $attribute => $content)
			$html .= " " . (is_string($attribute) ? htmlspecialchars($attribute) . '="' . htmlspecialchars($content) . '"' : $content);
		return $html;
	}

	static function multiple_values($multiple) {
		for ($values = array(), $i = 1; $multiple >= $i; $i <<= 1)
			if ($multiple & $i)
				$values[] = $i;
		return $values;
	}

}