<?php
namespace Subframe;

use Exception;

/**
 * Simple image functions
 * @package Subframe PHP Framework
 */
class Image {

	/** @var \GdImage|resource|null */
	private $image;

	/** @var bool */
	private $isModified = false;


	/**
	 * The constructor
	 * @param \GdImage|resource $image GD image
	 * @throws Exception
	 */
	public function __construct($image) {
		if (!$image || !imagesx($image))
			throw new Exception('Image source is not a GD image.', 500);

		$this->image = $image;
	}

	/**
	 * Constructs an image from a file
	 * @throws Exception
	 */
	public static function fromFile(string $filepath): self {
		if (!extension_loaded('gd'))
			throw new Exception('Image: GD PHP extension is required.', 500);
		if (!is_readable($filepath))
			throw new Exception("Image: cannot read $filepath.", 500);

		ini_set('gd.jpeg_ignore_warning', 1);

		$size = getimagesize($filepath);
		if (!$size)
			throw new Exception("Image: unsupported format in $filepath.", 500);
		[,, $type] = $size;

		if ($type == IMAGETYPE_JPEG || $type == IMAGETYPE_JPEG2000)
			$image = imagecreatefromjpeg($filepath);
		elseif ($type == IMAGETYPE_GIF)
			$image = imagecreatefromgif($filepath);
		elseif ($type == IMAGETYPE_PNG)
			$image = imagecreatefrompng($filepath);
		elseif ($type == IMAGETYPE_WEBP)
			$image = imagecreatefromwebp($filepath);
		elseif (defined('IMAGETYPE_AVIF') && $type == IMAGETYPE_AVIF && function_exists('imagecreatefromavif'))
			$image = imagecreatefromavif($filepath);
		else
			throw new Exception("Image: unsupported format in $filepath.", 500);
		if (!$image)
			throw new Exception("Image: cannot create image from $filepath.", 500);

		$image = new Image($image);

		if (function_exists('exif_read_data')) {
			$exif = @exif_read_data($filepath);
			$orientation = $exif['Orientation'] ?? null;
			$angle = ($orientation == 3 ? 180 :
					($orientation >= 5 && $orientation <= 7 ? -90 :
					($orientation == 8 ? 90 : 0)));
			if ($angle)
				$image->rotate($angle);
			if ($orientation == 2 || $orientation == 5)
				$image->flipHorizontally();
			if ($orientation == 4 || $orientation == 7)
				$image->flipVertically();
		}

		return $image;
	}

	/**
	 * Image's width
	 */
	public function getWidth(): int {
		return imagesx($this->image);
	}

	/**
	 * Image's height
	 */
	public function getHeight(): int {
		return imagesy($this->image);
	}

	/**
	 * Tells whether the image was resampled
	 */
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
		$srcWidth ??= $this->getWidth();
		$srcHeight ??= $this->getHeight();
		$dest = imagecreatetruecolor($destWidth, $destHeight);
		if (!$dest)
			throw new Exception('Cannot create new image.', 500);
		if (!imagecopyresampled($dest, $this->image, 0, 0, $srcX ?? 0, $srcY ?? 0, $destWidth, $destHeight, $srcWidth, $srcHeight))
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
	 * Flips the image horizontally
	 * @throws Exception
	 */
	public function flipHorizontally(): self {
		$isSuccess = imageflip($this->image, IMG_FLIP_HORIZONTAL);
		if (!$isSuccess)
			throw new Exception('Cannot flip image.', 500);
		$this->isModified = true;

		return $this;
	}

	/**
	 * Flips the image vertically
	 * @throws Exception
	 */
	public function flipVertically(): self {
		$isSuccess = imageflip($this->image, IMG_FLIP_VERTICAL);
		if (!$isSuccess)
			throw new Exception('Cannot flip image.', 500);
		$this->isModified = true;

		return $this;
	}

	/**
	 * Ensures the image doesn't exceed the given size, preserving the proportions, optionally enlarging it when smaller
	 * @param int $maxWidth
	 * @param int $maxHeight
	 * @param bool $canEnlarge
	 * @return Image
	 */
	public function contain(int $maxWidth, int $maxHeight, bool $canEnlarge = false): self {
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
	 * Resizes the image to the given size so it covers it completely; cuts out the excess if proportions differ
	 * @param int $width
	 * @param int $weight
	 * @return Image
	 */
	public function cover(int $width, int $weight): self {
		$originalWidth  = imagesx($this->image);
		$originalHeight = imagesy($this->image);
		$scale = max($width / $originalWidth, $weight / $originalHeight);
		$srcWidth = round($width / $scale);
		$srcHeight = round($weight / $scale);
		$srcX = round(($originalWidth - $srcWidth) / 2);
		$srcY = round(($originalHeight - $srcHeight) / 2);

		$this->resample($width, $weight, $srcX, $srcY, $srcWidth, $srcHeight);

		return $this;
	}

	/**
	 * Writes the image into a file or the output buffer
	 * @param string|null $filepath The path to save the file to, or null to output it into the buffer
	 * @param int $type PHP image type constant
	 * @param int $jpegQuality quality value from 0 (worst) to 100 (best)
	 * @throws Exception
	 */
	public function write(?string $filepath, int $type = IMAGETYPE_JPEG, int $jpegQuality = 98): self {
		if ($type == IMAGETYPE_GIF)
			$isSuccess = imagegif($this->image, $filepath);
		elseif ($type == IMAGETYPE_PNG)
			$isSuccess = imagepng($this->image, $filepath);
		else
			$isSuccess = imagejpeg($this->image, $filepath, $jpegQuality);
		if (!$isSuccess)
			throw new Exception("Image: cannot write to $filepath.", 500);

		return $this;
	}

}
