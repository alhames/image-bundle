<?php

namespace Alhames\ImageBundle;

use Alhames\ImageBundle\ImageEditProvider\GdImageEditProvider;
use GuzzleHttp\RequestOptions;
use PhpHelper\Str;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Symfony\Component\Filesystem\Filesystem;

class ImageManager
{
    public const HASH_DHASH = 'dhash';
    public const HASH_AHASH = 'ahash';
    public const HASH_PHASH_AVERAGE = 'phash_average';
    public const HASH_PHASH_MEDIAN = 'phash_median';

    private array $mimeTypes = [
        'image/png' => Image::TYPE_PNG,
        'image/gif' => Image::TYPE_GIF,
        'image/webp' => Image::TYPE_WEBP,
        'image/jpeg' => Image::TYPE_JPEG,
        'image/pjpeg' => Image::TYPE_JPEG,
        'image/bmp' => Image::TYPE_BMP,
        'image/x-ms-bmp' => Image::TYPE_BMP,
        'image/tiff' => Image::TYPE_TIFF,
        'image/x-icon' => Image::TYPE_ICO,
        'image/vnd.microsoft.icon' => Image::TYPE_ICO,
        'image/heif' => Image::TYPE_HEIF,
    ];
    private array $options = [
        'max_size' => 10_000_000, // 10 Mb
        'max_width' => 10_000, // 10,000 px
        'max_height' => 10_000, // 10,000 px
        'supported_types' => [Image::TYPE_JPEG, Image::TYPE_PNG, Image::TYPE_GIF, Image::TYPE_WEBP], // Supported image types
    ];
    private ClientInterface $httpClient;
    private Filesystem $fs;
    private GdImageEditProvider $provider;

    public function __construct(Filesystem $fs, ClientInterface $httpClient, array $options = [])
    {
        $this->fs = $fs;
        foreach ($options as $k => $v) {
            $this->options[$k] = $v;
        }
        $this->httpClient = $httpClient;
        $this->provider = new GdImageEditProvider();
    }

    /**
     * @return int|string|array
     */
    public function getOption(string $key)
    {
        return $this->options[$key];
    }

    /**
     * @param int|string|array $value
     */
    public function setOption(string $key, $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    public function isTypeSupported(string $type): bool
    {
        return in_array($type, $this->options['supported_types'], true);
    }

    public function createFromFileObject(\SplFileInfo $file): Image
    {
        if (!$file->isFile()) {
            throw new ImageException('The file does not exists.', ImageException::ERR_PATH);
        }
        if (!$file->isReadable()) {
            throw new ImageException('The file must be readable.', ImageException::ERR_READ);
        }

        return $this->create(null, $file->getRealPath());
    }

    public function createFromFilePath(string $path): Image
    {
        if (!is_file($path)) {
            throw new ImageException('The file does not exists.', ImageException::ERR_PATH);
        } elseif (!is_readable($path)) {
            throw new ImageException('The file must be readable.', ImageException::ERR_READ);
        }

        return $this->create(null, $path);
    }

    public function createFromUrl(string $url, array $options = []): Image
    {
        if (0 === stripos($url, 'data:')) {
            if (!preg_match('#^data:(//)?[^,]*?(?<base64>;base64)?,(?<data>.+)$#is', $url, $matches)) {
                throw new ImageException('Invalid Data URL.', ImageException::ERR_PATH);
            }

            return $this->create($matches['base64'] ? base64_decode($matches['data']) : $matches['data']);
        }

        if (0 === stripos($url, '//')) {
            $url = 'https:'.$url;
        }

        if (!Str::isUrl($url, true)) {
            throw new ImageException('Invalid URL.', ImageException::ERR_PATH);
        }

        // todo: убрать зависимость от Guzzle
        $options[RequestOptions::HEADERS]['Referer'] = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).'/';
        try {
            $response = $this->httpClient->request('GET', $url, $options);
        } catch (ClientExceptionInterface $e) {
            throw new ImageException('Can\'t load the image from URL.', ImageException::ERR_READ, $e);
        }

        if ($this->options['max_size'] < $response->getBody()->getSize()) {
            throw new ImageException('The file is too big.', ImageException::ERR_SIZE);
        }

        return $this->create((string) $response->getBody());
    }

    public function createFromString(string $string): Image
    {
        return $this->create($string);
    }

    public function saveTo(Image $image, string $directory, ?string $name = null): Image
    {
        try {
            if (!$this->fs->exists($directory)) {
                $this->fs->mkdir($directory);
            }

            $path = $directory.\DIRECTORY_SEPARATOR.($name ?? $image->getFullName());
            if ($this->fs->exists($path)) {
                throw new ImageException(sprintf('File %s already exists.', $path), ImageException::ERR_WRITE);
            }

            $filePath = $image->getFilePath();
            if (null !== $filePath) {
                if ($filePath !== $path) {
                    $this->fs->copy($filePath, $path);
                }
            } else {
                $this->fs->dumpFile($path, $image->getBinaryData());
            }

            return new Image([
                'size' => $image->getSize(),
                'mime-type' => $image->getMimeType(),
                'type' => $image->getType(),
                'width' => $image->getWidth(),
                'height' => $image->getHeight(),
                'hash' => $image->getMd5(true),
                'file' => $path,
                'data' => null,
            ]);
        } catch (\Throwable $e) {
            if ($e instanceof ImageException) {
                throw $e;
            } else {
                throw new ImageException($e->getMessage(), ImageException::ERR_WRITE, $e);
            }
        }
    }

    public function convertTo(EditableImage $image): Image
    {
        $sourceImage = $image->getSourceImage();
        if (!$image->isChanged()) {
            return $sourceImage;
        }

        if (null !== $image->getNewType() && !\in_array($image->getNewType(), $this->options['supported_types'], true)) {
            throw new ImageException(sprintf('Type "%s" is not supported.', $image->getNewType()), ImageException::ERR_TYPE);
        }
        if (null !== $image->getNewHeight()) {
            if ($image->getNewHeight() < 1) {
                throw new \InvalidArgumentException('Height must be 1 px or more.');
            }
            if ($image->getNewHeight() > $this->options['max_height']) {
                throw new \InvalidArgumentException(sprintf('Max height is %d px.', $this->options['max_height']));
            }
        }
        if (null !== $image->getNewWidth()) {
            if ($image->getNewWidth() < 1) {
                throw new \InvalidArgumentException('Width must be 1 px or more.');
            }
            if ($image->getNewWidth() > $this->options['max_width']) {
                throw new \InvalidArgumentException(sprintf('Max width is %d px.', $this->options['max_width']));
            }
        }

        $type = $image->getNewType() ?? $sourceImage->getType();
        $width = $image->getNewWidth() ?? $sourceImage->getWidth();
        $height = $image->getNewHeight() ?? $sourceImage->getHeight();
        $quality = $image->getQuality();

        $stream = $this->provider->convert($sourceImage, $type, $width, $height, $quality);
        $data = (string) $stream;

        return new Image([
            'size' => $stream->getSize(),
            'mime-type' => array_search($type, $this->mimeTypes, true),
            'type' => $type,
            'width' => $image->getNewWidth() ?? $sourceImage->getWidth(),
            'height' => $image->getNewHeight() ?? $sourceImage->getHeight(),
            'hash' => md5($data, true),
            'file' => null,
            'data' => $data,
        ]);
    }

    public function isAnimated(Image $image): bool
    {
        if (Image::TYPE_WEBP === $image->getType()) {
            // https://stackoverflow.com/questions/45190469/how-to-identify-whether-webp-image-is-static-or-animated
            $filePath = $image->getFilePath();
            if (null !== $filePath) {
                $file = new \SplFileObject($image->getFilePath(), 'rb');
                $file->fseek(12);
                if ('VP8X' === $file->fread(4)) {
                    $file->fseek(20);
                    $flag = $file->fread(1);
                }
                unset($file);
            } elseif ('VP8X' === substr($image->getBinaryData(), 12, 4)) {
                $flag = substr($image->getBinaryData(), 20, 1);
            }

            if (!isset($flag)) {
                return false;
            }

            return ((ord($flag) >> 1) & 1) ? true : false;
        }

        if (Image::TYPE_GIF === $image->getType()) {
            // https://stackoverflow.com/questions/280658/can-i-detect-animated-gifs-using-php-and-gd
            $filePath = $image->getFilePath();
            if (null !== $filePath) {
                $file = new \SplFileObject($image->getFilePath(), 'rb');
                $chunk = null;
                $frames = 0;
                while(!$file->eof() && $frames < 2) {
                    $chunk = (null !== $chunk ? substr($chunk, -20) : '').$file->fread(1024 * 100);
                    $frames += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk);
                }
                unset($file);
            } else {
                $frames = preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $image->getBinaryData());
            }

            return $frames > 1;
        }

        return false;
    }

    public function getHash(Image $image, string $type): ?string
    {
        if (in_array($type, [self::HASH_DHASH, self::HASH_AHASH, self::HASH_PHASH_AVERAGE, self::HASH_PHASH_MEDIAN], true)) {
            return $this->provider->getHash($image, $type);
        }

        $filePath = $image->getFilePath();
        if (null !== $filePath) {
            return hash_file($type, $filePath, true) ?: null;
        }

        return hash($type, $image->getBinaryData(), true) ?: null;
    }

    private function create(?string $data = null, ?string $file = null): Image
    {
        $info = [];
        $isFile = null !== $file;
        $info['size'] = $isFile ? filesize($file) : \strlen($data);
        if ($this->options['max_size'] < $info['size']) {
            throw new ImageException('The file is too big.', ImageException::ERR_SIZE);
        }

        $fInfo = new \finfo(FILEINFO_MIME_TYPE);
        $info['mime-type'] = $isFile ? $fInfo->file($file) : $fInfo->buffer($data);
        if (!isset($this->mimeTypes[$info['mime-type']])) {
            throw new ImageException(sprintf('Mime type "%s" is not supported.', $info['mime-type']), ImageException::ERR_TYPE);
        }

        $info['type'] = $this->mimeTypes[$info['mime-type']];
        if (!\in_array($info['type'], $this->options['supported_types'], true)) {
            throw new ImageException(sprintf('Type "%s" is not supported.', $info['type']), ImageException::ERR_TYPE);
        }

        $size = $isFile ? getimagesize($file) : getimagesizefromstring($data);
        if (!$size) {
            throw new ImageException('The file must be an image.', ImageException::ERR_TYPE);
        }
        $info['width'] = $size[0];
        $info['height'] = $size[1];
        if ($this->options['max_width'] < $info['width'] || $this->options['max_height'] < $info['height']) {
            $message = sprintf('Max resolution is %dx%d, %dx%d given.', $this->options['max_width'], $this->options['max_height'], $info['width'], $info['height']);
            throw new ImageException($message, ImageException::ERR_RESOLUTION);
        }

        $info['hash'] = $isFile ? md5_file($file, true) : md5($data, true);
        $info['file'] = $file;
        $info['data'] = $data;

        return new Image($info);
    }
}
