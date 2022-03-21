<?php

namespace Alhames\ImageBundle;

use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;

class Image
{
    public const TYPE_JPEG = 'jpeg';
    public const TYPE_PNG = 'png';
    public const TYPE_GIF = 'gif';
    public const TYPE_WEBP = 'webp';
    public const TYPE_BMP = 'bmp';
    public const TYPE_TIFF = 'tiff';
    public const TYPE_ICO = 'ico';
    public const TYPE_HEIF = 'heif';

    /** @var string|null Path to file. */
    protected ?string $file;
    /** @var string|null Binary content of image. */
    protected ?string $data;
    /** @var string Binary MD5 hash */
    protected string $hash;
    /** @var string Unique image name */
    protected string $name;
    /** @var string Mime type of image. */
    protected string $mimeType;
    /** @var string Image type */
    private string $type;
    /** @var int File size in bytes. */
    protected int $size;
    /** @var int Image width in pixels. */
    protected int $width;
    /** @var int Image height in pixels. */
    protected int $height;

    public function __construct(array $info)
    {
        $this->size = $info['size'];
        $this->mimeType = $info['mime-type'];
        $this->type = $info['type'];
        $this->width = $info['width'];
        $this->height = $info['height'];
        $this->hash = $info['hash'];
        $this->file = $info['file'];
        $this->data = $info['data'];
        $this->name = strtr(base64_encode($info['hash']), ['+' => '-', '/' => '_', '=' => '']);
    }

    public function getStream(): StreamInterface
    {
        return Utils::streamFor(null !== $this->file ? fopen($this->file, 'r') : $this->data, ['size' => $this->size]);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getFullName(): string
    {
        return $this->getName().'.'.$this->getType();
    }

    public function getMd5(bool $rawOutput = false): string
    {
        return $rawOutput ? $this->hash : bin2hex($this->hash);
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getExtension(): string
    {
        return $this->getType();
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getFilePath(): ?string
    {
        return $this->file;
    }

    public function getBinaryData(): ?string
    {
        return $this->data;
    }
}
