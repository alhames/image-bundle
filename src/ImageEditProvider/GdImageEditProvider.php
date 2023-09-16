<?php

namespace Alhames\ImageBundle\ImageEditProvider;

use Alhames\ImageBundle\Image;
use Alhames\ImageBundle\ImageManager as IM;
use GuzzleHttp\Psr7\Utils;
use Intervention\Image\ImageManager;
use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\AverageHash;
use Jenssegers\ImageHash\Implementations\DifferenceHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use Psr\Http\Message\StreamInterface;

class GdImageEditProvider
{
    /** @var bool Enable progressive jpeg */
    private bool $interlace = true;
    /** @var array RGB color of background */
    private array $background = [255, 255, 255];

    public function convert(Image $sourceImage, string $type, int $destinationWidth, int $destinationHeight, ?int $quality = null): StreamInterface
    {
        $destinationRatio = $destinationWidth / $destinationHeight;
        $sourceWidth = $sourceImage->getWidth();
        $sourceHeight = (int) floor($sourceWidth / $destinationRatio);
        if ($sourceHeight > $sourceImage->getHeight()) {
            $sourceHeight = $sourceImage->getHeight();
            $sourceWidth = (int) floor($sourceHeight * $destinationRatio);
        }
        $leftOffset = (int) floor(($sourceImage->getWidth() - $sourceWidth) / 2);
        $topOffset = (int) floor(($sourceImage->getHeight() - $sourceHeight) / 2);

        $image = imagecreatetruecolor($destinationWidth, $destinationHeight);
        $color = imagecolorallocate($image, ...$this->background);
        imagefill($image, 0, 0, $color);
        if (Image::TYPE_PNG === $type || Image::TYPE_WEBP === $type) {
            imagealphablending($image, false);
            imagesavealpha($image, true);
        }

        $result = imagecopyresampled(
            $image,
            $this->getResource($sourceImage),
            0,
            0,
            $leftOffset,
            $topOffset,
            $destinationWidth,
            $destinationHeight,
            $sourceWidth,
            $sourceHeight
        );
        if (!$result) {
            throw new \RuntimeException('Unable to resize image.');
        }

        if (Image::TYPE_JPEG === $type) {
            imageinterlace($image, $this->interlace);
        }

        $resource = fopen('php://temp', 'r+');
        $saveFunction = 'image'.$type;
        if (null !== $quality && Image::TYPE_GIF !== $type) {
            $saveFunction($image, $resource, $quality);
        } else {
            $saveFunction($image, $resource);
        }
        imagedestroy($image);

        return Utils::streamFor($resource);
    }

    public function getHash(Image $image, string $type): string
    {
        static $hashAlgorithms;
        static $driver;
        if (empty($hashAlgorithms)) {
            $hashAlgorithms = [
                IM::HASH_DHASH => new DifferenceHash(),
                IM::HASH_AHASH => new AverageHash(),
                IM::HASH_PHASH_AVERAGE => new PerceptualHash(32, PerceptualHash::AVERAGE),
                IM::HASH_PHASH_MEDIAN => new PerceptualHash(32, PerceptualHash::MEDIAN),
            ];
            $driver = new ImageManager(['driver' => 'gd']);
        }

        $resource = $this->getResource($image);
        $hasher = new ImageHash($hashAlgorithms[$type], $driver);

        return $hasher->hash($resource)->toBytes();
    }

    /**
     * @todo
     * @see http://www.phash.org/
     * @see https://habr.com/ru/post/120562/
     * @see https://github.com/jenssegers/imagehash/blob/master/src/Implementations/PerceptualHash.php
     */
    public function getPerceptualHash(Image $image): string
    {
        $resource = $this->getResource($image);
        $size = 8;
        $sampleSize = $size * 4;

        $sample = imagecreatetruecolor($sampleSize, $sampleSize);
        imagealphablending($sample, false);
        imagesavealpha($sample, true);
        $success = imagecopyresampled($sample, $resource, 0, 0, 0, 0, $sampleSize, $sampleSize, $image->getWidth(), $image->getHeight());
        if (!$success) {
            throw new \RuntimeException('Can\'t create the sample.');
        }

        $matrix = [];
        $rows = [];
        $row = [];
        for ($y = 0; $y < $sampleSize; ++$y) {
            for ($x = 0; $x < $sampleSize; ++$x) {
                $colorIndex = imagecolorat($sample, $x, $y);
                $color = imagecolorsforindex($sample, $colorIndex);
                $row[$x] = (int) floor(($color['red'] * 0.299) + ($color['green'] * 0.587) + ($color['blue'] * 0.114));
            }
            $rows[$y] = $this->calculateDCT($row);
        }

        $col = [];
        for ($x = 0; $x < $sampleSize; ++$x) {
            for ($y = 0; $y < $sampleSize; ++$y) {
                $col[$y] = $rows[$y][$x];
            }
            $matrix[$x] = $this->calculateDCT($col);
        }

        // Extract the top 8x8 pixels.
        $pixels = [];
        for ($y = 0; $y < $size; ++$y) {
            for ($x = 0; $x < $size; ++$x) {
                $pixels[] = $matrix[$y][$x];
            }
        }

        $n = \count($pixels) - 1;
        $compare = array_sum(\array_slice($pixels, 1, $n)) / $n;

        $bits = '';
        foreach ($pixels as $pixel) {
            $bits .= ($pixel > $compare) ? '1' : '0';
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            $binary .= \chr(\intval($byte, 2));
        }

        return $binary;
    }

    /**
     * Perform a 1 dimension Discrete Cosine Transformation.
     */
    private function calculateDCT(array $matrix): array
    {
        $transformed = [];
        $size = \count($matrix);
        for ($i = 0; $i < $size; ++$i) {
            $sum = 0;
            for ($j = 0; $j < $size; ++$j) {
                $sum += $matrix[$j] * cos($i * M_PI * ($j + 0.5) / $size);
            }
            $sum *= sqrt(2 / $size);
            if (0 === $i) {
                $sum *= 1 / sqrt(2);
            }
            $transformed[$i] = $sum;
        }

        return $transformed;
    }

    /**
     * @return resource
     */
    private function getResource(Image $image)
    {
        if (null === $image->getFilePath()) {
            return imagecreatefromstring($image->getBinaryData());
        }

        $function = 'imagecreatefrom'.$image->getType();

        return $function($image->getFilePath());
    }
}
