<?php
/**
 * @package   FediverseForJoomla
 * @copyright Copyright (c)2023 Nicholas K. Dionysopoulos
 * @license   https://opensource.org/licenses/GPL-3.0 GPL-3.0-or-later
 */

namespace Dionysopoulos\Plugin\Content\ContentActivityPub\Extension;

\defined('_JEXEC') || die;

use Exception;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Image\Image;
use Joomla\Registry\Registry;
use kornrunner\Blurhash\Blurhash;

trait ImageHandlingTrait
{
	/**
	 * Maximum pixels in the largest image dimension to sample for BlurHash.
	 *
	 * Smaller values are faster but the BlurHash is less accurate. Higher values are more accurate but far slower AND
	 * use a lot of memory. Values between 32 and 128 work best, based on a subjective trial against a few dozen photos
	 * and illustrations I had at hand.
	 *
	 * @since  2.0.0
	 */
	private static $maxHashPixels = 64;

	/**
	 * Cache of BlurHash keyed by image location, to speed things up a smidge.
	 *
	 * @var    array
	 * @since  2.0.0
	 */
	private static array $blurHashCache = [];

	/**
	 * Attaches images to the source object
	 *
	 * @param   string  $imagesSource  The JSON-encoded information about the article's images
	 * @param   string  $sourceType    Where does the Activity get the source of its content?
	 *
	 * @return  array
	 * @since   2.0.0
	 */
	private function getImageAttachments(string $imagesSource, string $sourceType): array
	{
		$ret           = [];
		$params        = new Registry($imagesSource);
		$introImage    = $params->get('image_intro');
		$introAlt      = $params->get('image_intro_alt');
		$fulltextImage = $params->get('image_filltext');
		$fulltextAlt   = $params->get('image_filltext_alt');

		if ($sourceType !== 'fulltext' && !empty($introImage))
		{
			$ret[] = $this->getImageAttachment($introImage, $introAlt);
		}

		if ($sourceType !== 'introtext' && !empty($fulltextImage))
		{
			$ret[] = $this->getImageAttachment($fulltextImage, $fulltextAlt);
		}

		return $ret;
	}

	/**
	 * Get an array representing an Image object given some image data.
	 *
	 * @param   string|null  $imageSource  The Joomla image source.
	 * @param   string|null  $altText      The alt text of the image.
	 *
	 * @return  array|null  The Image object; NULL if the image cannot be processed
	 * @throws  Exception
	 * @since   2.0.0
	 */
	private function getImageAttachment(?string $imageSource, ?string $altText): ?array
	{
		// No image?
		if (empty($imageSource))
		{
			return null;
		}

		// Invalid image?
		$info = HTMLHelper::cleanImageURL($imageSource);

		try
		{
			$props = Image::getImageFileProperties($info->url);
		}
		catch (Exception $e)
		{
			$props = null;
		}

		try
		{
			$props = $props ?? Image::getImageFileProperties(JPATH_ROOT . '/' . ltrim($info->url, '/'));
		}
		catch (Exception $e)
		{
			return null;
		}

		$url = str_starts_with($info->url, 'http://') || str_starts_with($info->url, 'https://')
			? $info->url
			: ($this->getFrontendBasePath() . '/' . ltrim($info->url, '/'));

		return [
			'type'      => 'Image',
			'mediaType' => $props->mime,
			'url'       => $url,
			'name'      => $altText ?? '',
			'blurhash'  => $this->getBlurHash($info->url),
			'width'     => $info->attributes['width'] ?? 0,
			'height'    => $info->attributes['height'] ?? 0,
		];
	}

	/**
	 * Calculates the BlurHash of an image file
	 *
	 * @param   string  $file  The URL or path to the file
	 *
	 * @return  string  The BlurHash; empty string if it cannot be calculated.
	 * @since   2.0.0
	 */
	private function getBlurHash(string $file): string
	{
		$key = md5($file);

		if (isset(self::$blurHashCache[$key]))
		{
			return self::$blurHashCache[$key];
		}

		$path = str_starts_with($file, 'http://') || str_starts_with($file, 'https://')
			? $file
			: JPATH_ROOT . '/' . ltrim($file, '/');

		if (
			!function_exists('imagecreatefromstring')
			|| !function_exists('imagesx')
			|| !function_exists('imagesy')
			|| !function_exists('imagecolorat')
			|| !function_exists('imagecolorsforindex')
			|| !function_exists('imagedestroy')
		)
		{
			return self::$blurHashCache[$key] = '';
		}

		$imageContents = file_get_contents($path);

		if ($imageContents === false)
		{
			return self::$blurHashCache[$key] = '';
		}

		$image = imagecreatefromstring($imageContents);

		if ($image === false)
		{
			return self::$blurHashCache[$key] = '';
		}

		$width  = imagesx($image);
		$height = imagesy($image);
		$pixels = [];

		$aspectRatio = $width / $height;

		if ($aspectRatio >= 1)
		{
			$maxWidth  = self::$maxHashPixels;
			$maxHeight = floor(self::$maxHashPixels / $aspectRatio);
		}
		else
		{
			$maxWidth  = floor(self::$maxHashPixels * $aspectRatio);
			$maxHeight = self::$maxHashPixels;
		}

		$stepsX = floor($width / $maxWidth);
		$stepsY = floor($height / $maxHeight);

		for ($y = 0; $y < $height; $y += $stepsY)
		{
			$row = [];

			for ($x = 0; $x < $width; $x += $stepsX)
			{
				$index  = imagecolorat($image, $x, $y);
				$colors = imagecolorsforindex($image, $index);

				$row[] = [$colors['red'], $colors['green'], $colors['blue']];
			}

			$pixels[] = $row;
		}

		imagedestroy($image);

		$components_x = 4;
		$components_y = 3;

		return self::$blurHashCache[$key] = Blurhash::encode($pixels, $components_x, $components_y);
	}
}