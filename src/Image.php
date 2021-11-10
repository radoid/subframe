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
	 * @param $srcX
	 * @param $srcY
	 * @param $srcWidth
	 * @param $srcHeight
	 * @param $destWidth
	 * @param $destHeight
	 * @param string $destination
	 * @param int $destinationType
	 * @param int $rotation
	 * @return string destination path or false in case of an error
	 */
	static function resample($source, $srcX, $srcY, $srcWidth, $srcHeight, $destWidth, $destHeight, $destination = '', $destinationType = 0, $rotation = 0) {
		list ($initialWidth, $initialHeight, $type) = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		ini_set('memory_limit', '256M');
		ini_set('gd.jpeg_ignore_warning', 1);

		if ($type == IMAGETYPE_GIF)
			$imageBefore = imagecreatefromgif($source);
		elseif ($type == IMAGETYPE_PNG)
			$imageBefore = imagecreatefrompng($source);
		elseif ($type == IMAGETYPE_BMP || $type == IMAGETYPE_WBMP)
			$imageBefore = imagecreatefromwbmp($source);
		elseif ($type == IMAGETYPE_WEBP)
			$imageBefore = imagecreatefromwebp($source);
		else
			$imageBefore = imagecreatefromjpeg($source);
		if (!$imageBefore)
			return false;

		if (!($imageAfter = imagecreatetruecolor($destWidth, $destHeight)))
			return false;
		if (!imagecopyresampled($imageAfter, $imageBefore, 0, 0, $srcX, $srcY, $destWidth, $destHeight, $srcWidth, $srcHeight))
			return false;

		if ($rotation)
			$imageAfter = imagerotate($imageAfter, $rotation, 0);

		$destination = ($destination ?: $source);
		$destinationType = ($destinationType ?: $type);
		if ($destinationType == IMAGETYPE_GIF)
			$success = imagegif($imageAfter, $destination);
		elseif ($destinationType == IMAGETYPE_PNG)
			$success = imagepng($imageAfter, $destination);
		else
			$success = imagejpeg($imageAfter, $destination, 98);
		if (!$success)
			return false;

		return $destination;
	}

	/**
	 * Ensures the image doesn't exceed the given size
	 * @param $source
	 * @param $maxWidth
	 * @param $maxHeight
	 * @param string $destination
	 * @param int $destinationType
	 * @param bool $canCrop
	 * @return string destination path or false in case of an error
	 */
	static function constrain($source, $maxWidth, $maxHeight, $destination = '', $destinationType = 0, $canCrop = false) {
		list ($initialWidth, $initialHeight, $type) = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		$rotation = (function_exists('exif_read_data') && ($exif = @exif_read_data($source)) && ($orientation = @$exif['Orientation']) ?
						($orientation == 8 ? 90 : ($orientation == 3 ? 180 : ($orientation == 6 ? -90 : 0))) : 0);
		if ($rotation == 90 || $rotation == -90)
			list($maxWidth, $maxHeight) = [$maxHeight, $maxWidth];

		if ($initialWidth <= $maxWidth && $initialHeight <= $maxHeight  // if the file doesn't change
				&& (!$destinationType || $destinationType == $type)
				&& (!$destination || $destination == $source))
			return $source;

		if ($canCrop) {  // take only the center part to fit new dimensions
			$destWidth = $maxWidth;
			$destHeight = $maxHeight;
			$scale = max($destWidth / $initialWidth, $destHeight / $initialHeight);
			$srcWidth = $destWidth / $scale;
			$srcHeight = $destHeight / $scale;
			$srcX = ($initialWidth - $srcWidth) / 2;
			$srcY = ($initialHeight - $srcHeight) / 2;
		} else {  // all of the image must fit into the new dimensions
			$scale = min(min($initialWidth, $maxWidth) / $initialWidth, min($initialHeight, $maxHeight) / $initialHeight);
			$destWidth = $initialWidth * $scale;
			$destHeight = $initialHeight * $scale;
			$srcWidth = $initialWidth;
			$srcHeight = $initialHeight;
			$srcX = 0;
			$srcY = 0;
		}

		return self::resample($source, $srcX, $srcY, $srcWidth, $srcHeight, $destWidth, $destHeight, $destination, $destinationType, $rotation);
	}

	/**
	 * Rotates an image
	 * @param $source
	 * @param $angle
	 * @param string $destination
	 * @param int $destinationType
	 * @return bool|string
	 */
	static function rotate($source, $angle, $destination = '', $destinationType = 0) {
		list ($initialWidth, $initialHeight, $type) = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		ini_set("memory_limit", "256M");
		ini_set('gd.jpeg_ignore_warning', 1);

		if ($type == IMAGETYPE_GIF)
			$imageBefore = imagecreatefromgif($source);
		elseif ($type == IMAGETYPE_PNG)
			$imageBefore = imagecreatefrompng($source);
		elseif ($type == IMAGETYPE_BMP || $type == IMAGETYPE_WBMP)
			$imageBefore = imagecreatefromwbmp($source);
		else
			$imageBefore = imagecreatefromjpeg($source);
		if (!$imageBefore)
			return false;

		if (!($imageAfter = imagerotate($imageBefore, $angle, 0)))
			return false;

		$destination = ($destination ?: $source);
		$destinationType = ($destinationType ?: $type);
		if ($destinationType == IMAGETYPE_GIF)
			$success = imagegif($imageAfter, $destination);
		elseif ($destinationType == IMAGETYPE_PNG)
			$success = imagepng($imageAfter, $destination);
		else
			$success = imagejpeg($imageAfter, $destination, 98);
		if (!$success)
			return false;

		return $destination;
	}

}
