<?php
/**
 * Image functions
 *
 * @package Subframe PHP Framework
 */
namespace Subframe;

class Image
{
	/**
	 * Tests if the given file is a readable image
	 */
	static function test($path) {
		return ($path && is_file($path) && (@list ($width, $height, $type) = @getimagesize($path)) && $width && $height && $type);
	}

	/**
	 * Crops a part of an image and saves it resized
	 * @param $source
	 * @param $src_x
	 * @param $src_y
	 * @param $src_w
	 * @param $src_h
	 * @param $dst_w
	 * @param $dst_h
	 * @param bool $destination
	 * @param int $destinationtype
	 * @return string destination path or false in case of an error
	 */
	static function crop($source, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $destination = false, $destinationtype = 0) {
		list ($initialwidth, $initialheight, $type) = @getimagesize($source);
		if (!$initialwidth || !$initialheight || !$type)
			return false;

		ini_set('memory_limit', '256M');
		ini_set('gd.jpeg_ignore_warning', 1);

		if ($type == IMAGETYPE_GIF)
			$image_before = imagecreatefromgif($source);
		elseif ($type == IMAGETYPE_PNG)
			$image_before = imagecreatefrompng($source);
		elseif ($type == IMAGETYPE_BMP || $type == IMAGETYPE_WBMP)
			$image_before = imagecreatefromwbmp($source);
		else
			$image_before = imagecreatefromjpeg($source);
		$image_after = imagecreatetruecolor($dst_w, $dst_h);
		if (!$image_after || !$image_before)
			return false;
		if (!imagecopyresampled($image_after, $image_before, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h))
			return false;

		$destination = ($destination ? $destination : $source);
		$destinationtype = ($destinationtype ? $destinationtype : $type);
		if ($destinationtype == IMAGETYPE_GIF)
			$success = imagegif($image_after, $destination);
		elseif ($destinationtype == IMAGETYPE_PNG)
			$success = imagepng($image_after, $destination);
		else
			$success = imagejpeg($image_after, $destination, 98);
		if (!$success)
			return false;
		@chmod($destination, 0666);
		return $destination;
	}

	/**
	 * Ensures the image fits the given size. It's possible to limit only width or height if given zero-value.
	 * @param $source
	 * @param $maxwidth
	 * @param $maxheight
	 * @param bool $destination
	 * @param int $destinationtype
	 * @param bool $crop
	 * @return string destination path or false in case of an error
	 */
	static function constrain($source, $maxwidth, $maxheight, $destination = false, $destinationtype = 0, $crop = false) {
		list ($initialwidth, $initialheight, $type) = @getimagesize($source);
		if (!$initialwidth || !$initialheight || !$type)
			return false;
		if ($initialwidth <= $maxwidth && $initialheight <= $maxheight && !$destination)
			return $source;

		if ($crop) { // uzimamo samo središnji dio slike, za nove proporcije
			$dst_w = $maxwidth;
			$dst_h = $maxheight;
			$factor = max($dst_w / $initialwidth, $dst_h / $initialheight);
			$src_w = $dst_w / $factor;
			$src_h = $dst_h / $factor;
			$src_x = ($initialwidth - $src_w) / 2;
			$src_y = ($initialheight - $src_h) / 2;
		} else { // čitava slika mora stati unutar danih ograničenja i zadržati proporcije, ne radimo stretch
			$factor = min(min($initialwidth, $maxwidth) / $initialwidth, min($initialheight, $maxheight) / $initialheight);
			$dst_w = $initialwidth * $factor;
			$dst_h = $initialheight * $factor;
			$src_w = $initialwidth;
			$src_h = $initialheight;
			$src_x = 0;
			$src_y = 0;
		}

		return self::crop($source, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $destination, $destinationtype);
	}

}