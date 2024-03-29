<?php
namespace Subframe;

use Exception;

/**
 * Simple image functions
 * @package Subframe PHP Framework
 */
class Image {

	/** @var resource|null */
	private $image;

	/** @var int */
	private $width, $height;

	/** @var array|null */
	private $exif = null;

	/** @var bool */
	private $isModified = false;


	/**
	 * The constructor
	 * @param string $source Image file path
	 * @throws Exception
	 */
	public function __construct(string $source) {
		if (!extension_loaded('gd'))
			throw new Exception('GD PHP extension is required.', 500);
		if (!is_readable($source))
			throw new Exception("File $source not found.", 500);

		ini_set('memory_limit', '-1');
		ini_set('gd.jpeg_ignore_warning', 1);

		$size = getimagesize($source);
		if (!$size)
			throw new Exception("File $source is not an image.", 500);
		[$this->width, $this->height, $type] = $size;
		if (!$this->width || !$this->height || !$type)
			throw new Exception("File $source is not an image.", 500);

		if (function_exists('exif_read_data'))
			$this->exif = @exif_read_data($source) ?: null;

		if ($type == IMAGETYPE_GIF)
			$this->image = imagecreatefromgif($source);
		elseif ($type == IMAGETYPE_PNG)
			$this->image = imagecreatefrompng($source);
		elseif ($type == IMAGETYPE_BMP || $type == IMAGETYPE_WBMP)
			$this->image = imagecreatefromwbmp($source);
		elseif ($type == IMAGETYPE_WEBP)
			$this->image = imagecreatefromwebp($source);
		elseif ($type == IMAGETYPE_JPEG || $type == IMAGETYPE_JPEG2000)
			$this->image = imagecreatefromjpeg($source);
		else
			throw new Exception("Unsupported image format in $source.", 500);
		if (!$this->image)
			throw new Exception("Cannot create image from $source.", 500);

		$orientation = $this->exif['Orientation'] ?? null;
		$angle = ($orientation == 8 ? +90 : ($orientation == 3 ? +180 : ($orientation == 6 ? -90 : 0)));
		if ($angle)
			$this->rotate($angle);
	}

	public function getWidth() {
		return imagesx($this->image);
	}

	public function getHeight() {
		return imagesy($this->image);
	}

	public function getExifData(string $key) {
		return $this->exif[$key] ?? null;
	}

	public function isModified(): bool {
		return $this->isModified;
	}

	/**
	 * Takes an image or part of it and resamples it into the given area
	 * @param int $destWidth
	 * @param int $destHeight
	 * @param int|null $srcX
	 * @param int|null $srcY
	 * @param int|null $srcWidth
	 * @param int|null $srcHeight
	 * @return Image
	 * @throws Exception
	 */
	public function resample(int $destWidth, int $destHeight, ?int $srcX = null, ?int $srcY = null, ?int $srcWidth = null, ?int $srcHeight = null): self {
		$dest = imagecreatetruecolor($destWidth, $destHeight);
		if (!$dest)
			throw new Exception('Cannot create new image.', 500);
		if (!imagecopyresampled($dest, $this->image, 0, 0, $srcX ?? 0, $srcY ?? 0, $destWidth, $destHeight, $srcWidth ?? $this->width, $srcHeight ?? $this->height))
			throw new Exception('Cannot resample the image.', 500);
		$this->image = $dest;
		$this->isModified = true;

		return $this;
	}

	/**
	 * Rotates the image
	 * @param float $angle Rotation angle in degrees, anti-clockwise
	 * @throws Exception
	 */
	public function rotate(float $angle): self {
		$this->image = imagerotate($this->image, $angle, 0);
		if (!$this->image)
			throw new Exception('Cannot rotate image.', 500);
		$this->isModified = true;

		return $this;
	}

	/**
	 * Ensures the image doesn't exceed the given size, preserving the proportions, optionally enlarging it when smaller
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param bool $canEnlarge
	 * @return Image
	 * @throws Exception
	 */
	function contain(int $maxWidth, int $maxHeight, bool $canEnlarge = false): self {
		$width  = imagesx($this->image);
		$height = imagesy($this->image);
		if ($canEnlarge || $width > $maxWidth || $height > $maxHeight) {
			$scale = min($maxWidth / $width, $maxHeight / $height);
			$destWidth = round($width * $scale);
			$destHeight = round($height * $scale);

			$this->resample($destWidth, $destHeight, 0, 0, $width, $height);
		}

		return $this;
	}

	/**
	 * Ensures the image doesn't exceed the given size, cropping the center part if needed
	 * to best fill the given size, optionally enlarging it when smaller
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param bool $canEnlarge
	 * @return Image
	 * @throws Exception
	 */
	function cover(int $maxWidth, int $maxHeight, bool $canEnlarge = true): self {
		$width  = imagesx($this->image);
		$height = imagesy($this->image);
		if ($canEnlarge || $width > $maxWidth || $height > $maxHeight) {
			$scale = max($maxWidth / $width, $maxHeight / $height);
			$srcWidth = round($maxWidth / $scale);
			$srcHeight = round($maxHeight / $scale);
			$srcX = round(($width - $srcWidth) / 2);
			$srcY = round(($height - $srcHeight) / 2);

			$this->resample($maxWidth, $maxHeight, $srcX, $srcY, $srcWidth, $srcHeight);
		}

		return $this;
	}

	/**
	 * Saves the image into a file
	 * @param string|null $destination The path to save the file to
	 * @param int $destinationType PHP image type constant
	 * @param int $jpegQuality quality value from 0 (worst) to 100 (best), if JPEG type
	 * @throws Exception
	 */
	function save(string $destination, int $destinationType = IMAGETYPE_JPEG, int $jpegQuality = 98): self {
		if ($destinationType == IMAGETYPE_GIF)
			$isSuccess = imagegif($this->image, $destination);
		elseif ($destinationType == IMAGETYPE_PNG)
			$isSuccess = imagepng($this->image, $destination);
		else
			$isSuccess = imagejpeg($this->image, $destination, $jpegQuality);
		if (!$isSuccess)
			throw new Exception("Cannot write to $destination.", 500);

		return $this;
	}

}
