<?php
namespace Subframe;

/**
 * Image functions
 * @package Subframe PHP Framework
 */
class Image {

	/**
	 * Takes an image or part of it and saves it in another size, optionally rotated too
	 * @param string $source
	 * @param int $srcX
	 * @param int $srcY
	 * @param int $srcWidth
	 * @param int $srcHeight
	 * @param int $destWidth
	 * @param int $destHeight
	 * @param string|null $destination
	 * @param int $destinationType
	 * @param float $rotationAngle
	 * @param int $quality
	 * @return bool true if successful or false in case of an error
	 */
	static function resample(string $source, int $srcX, int $srcY, int $srcWidth, int $srcHeight, int $destWidth, int $destHeight, ?string $destination = null, ?int $destinationType = null, float $rotationAngle = 0, int $quality = 98): bool {
		[$initialWidth, $initialHeight, $type] = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		ini_set('memory_limit', '-1');
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

		if ($rotationAngle)
			$imageAfter = imagerotate($imageAfter, $rotationAngle, 0);

		$destination = ($destination ?: $source);
		$destinationType = ($destinationType ?: $type);
		if ($destinationType == IMAGETYPE_GIF)
			$isSuccess = imagegif($imageAfter, $destination);
		elseif ($destinationType == IMAGETYPE_PNG)
			$isSuccess = imagepng($imageAfter, $destination);
		else
			$isSuccess = imagejpeg($imageAfter, $destination, $quality);

		return $isSuccess;
	}

	/**
	 * Ensures the image doesn't exceed the given size
	 * @param string $source
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param string|null $destination
	 * @param int $destinationType
	 * @param bool $canCrop
	 * @param int $quality
	 * @return bool true if successful or false in case of an error
	 */
	static function constrain(string $source, int $maxWidth, int $maxHeight, ?string $destination = null, ?int $destinationType = null, bool $canCrop = false, int $quality = 98): bool {
		[$initialWidth, $initialHeight, $type] = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		$rotationAngle = (function_exists('exif_read_data') && ($exif = @exif_read_data($source)) && ($orientation = @$exif['Orientation']) ?
						($orientation == 8 ? 90 : ($orientation == 3 ? 180 : ($orientation == 6 ? -90 : 0))) : 0);
		if ($rotationAngle == 90 || $rotationAngle == -90)
			[$maxWidth, $maxHeight] = [$maxHeight, $maxWidth];

		if ($initialWidth <= $maxWidth && $initialHeight <= $maxHeight  // if the file doesn't change
				&& (!$destinationType || $destinationType == $type)
				&& (!$destination || $destination == $source))
			return true;

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

		return self::resample($source, round($srcX), round($srcY), round($srcWidth), round($srcHeight), round($destWidth), round($destHeight), $destination, $destinationType, $rotationAngle, $quality);
	}

	/**
	 * Rotates an image
	 * @param string $source
	 * @param float $angle
	 * @param string|null $destination
	 * @param int $destinationType
	 * @param int $quality
	 * @return bool true if successful or false in case of an error
	 */
	static function rotate(string $source, float $angle, ?string $destination = null, ?int $destinationType = null, int $quality = 98): bool {
		[$initialWidth, $initialHeight, $type] = @getimagesize($source);
		if (!$initialWidth || !$initialHeight || !$type)
			return false;

		ini_set("memory_limit", "-1");
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
			$isSuccess = imagegif($imageAfter, $destination);
		elseif ($destinationType == IMAGETYPE_PNG)
			$isSuccess = imagepng($imageAfter, $destination);
		else
			$isSuccess = imagejpeg($imageAfter, $destination, $quality);

		return $isSuccess;
	}

}
