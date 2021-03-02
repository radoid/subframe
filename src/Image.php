<?php
namespace Subframe;

/**
 * Image functions
 * @package Subframe PHP Framework
 */
class Image {

	/**
	 * Takes an image or part of it and saves it in another size, optionally rotated too
	 * @param $source
	 * @param $src_x
	 * @param $src_y
	 * @param $src_w
	 * @param $src_h
	 * @param $dst_w
	 * @param $dst_h
	 * @param string $destination
	 * @param int $destinationtype
	 * @param int $rotation
	 * @return string destination path or false in case of an error
	 */
	static function resample($source, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $destination = '', $destinationtype = 0, $rotation = 0) {
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
		if (!$image_before)
			return false;

		if (!($image_after = imagecreatetruecolor($dst_w, $dst_h)))
			return false;
		if (!imagecopyresampled($image_after, $image_before, 0, 0, $src_x, $src_y, $dst_w, $dst_h, $src_w, $src_h))
			return false;

		if ($rotation)
			$image_after = imagerotate($image_after, $rotation, 0);

		$destination = ($destination ?: $source);
		$destinationtype = ($destinationtype ?: $type);
		if ($destinationtype == IMAGETYPE_GIF)
			$success = imagegif($image_after, $destination);
		elseif ($destinationtype == IMAGETYPE_PNG)
			$success = imagepng($image_after, $destination);
		else
			$success = imagejpeg($image_after, $destination, 98);
		if (!$success)
			return false;

		return $destination;
	}

	/**
	 * Ensures the image doesn't exceed the given size
	 * @param $source
	 * @param $maxwidth
	 * @param $maxheight
	 * @param string $destination
	 * @param int $destinationtype
	 * @param bool $canCrop
	 * @return string destination path or false in case of an error
	 */
	static function constrain($source, $maxwidth, $maxheight, $destination = '', $destinationtype = 0, $canCrop = false) {
		list ($initialwidth, $initialheight, $type) = @getimagesize($source);
		if (!$initialwidth || !$initialheight || !$type)
			return false;

		$rotation = (function_exists('exif_read_data') && ($exif = @exif_read_data($source)) && ($orientation = @$exif['Orientation']) ?
						($orientation == 8 ? 90 : ($orientation == 3 ? 180 : ($orientation == 6 ? -90 : 0))) : 0);
		if ($rotation == 90 || $rotation == -90)
			list($maxwidth, $maxheight) = [$maxheight, $maxwidth];

		if ($initialwidth <= $maxwidth && $initialheight <= $maxheight && !$destinationtype)
			return $source;

		if ($canCrop) {  // take only the center part to fit new dimensions
			$dst_w = $maxwidth;
			$dst_h = $maxheight;
			$factor = max($dst_w / $initialwidth, $dst_h / $initialheight);
			$src_w = $dst_w / $factor;
			$src_h = $dst_h / $factor;
			$src_x = ($initialwidth - $src_w) / 2;
			$src_y = ($initialheight - $src_h) / 2;
		} else {  // all of the image must fit into the new dimensions
			$factor = min(min($initialwidth, $maxwidth) / $initialwidth, min($initialheight, $maxheight) / $initialheight);
			$dst_w = $initialwidth * $factor;
			$dst_h = $initialheight * $factor;
			$src_w = $initialwidth;
			$src_h = $initialheight;
			$src_x = 0;
			$src_y = 0;
		}

		return self::resample($source, $src_x, $src_y, $src_w, $src_h, $dst_w, $dst_h, $destination, $destinationtype, $rotation);
	}

	/**
	 * Rotates an image
	 * @param $source
	 * @param $angle
	 * @param string $destination
	 * @param int $destinationtype
	 * @return bool|string
	 */
	static function rotate($source, $angle, $destination = '', $destinationtype = 0) {
		list ($initialwidth, $initialheight, $type) = @getimagesize($source);
		if (!$initialwidth || !$initialheight || !$type)
			return false;

		ini_set("memory_limit", "256M");
		ini_set('gd.jpeg_ignore_warning', 1);

		if ($type == IMAGETYPE_GIF)
			$image_before = imagecreatefromgif($source);
		elseif ($type == IMAGETYPE_PNG)
			$image_before = imagecreatefrompng($source);
		elseif ($type == IMAGETYPE_BMP || $type == IMAGETYPE_WBMP)
			$image_before = imagecreatefromwbmp($source);
		else
			$image_before = imagecreatefromjpeg($source);
		if (!$image_before)
			return false;

		if (!($image_after = imagerotate($image_before, $angle, 0)))
			return false;

		$destination = ($destination ?: $source);
		$destinationtype = ($destinationtype ?: $type);
		if ($destinationtype == IMAGETYPE_GIF)
			$success = imagegif($image_after, $destination);
		elseif ($destinationtype == IMAGETYPE_PNG)
			$success = imagepng($image_after, $destination);
		else
			$success = imagejpeg($image_after, $destination, 98);
		if (!$success)
			return false;

		return $destination;
	}

}
