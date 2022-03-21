<?php

namespace Alhames\ImageBundle;

class EditableImage
{
    private Image $image;
    private ?string $newType = null;
    private ?int $quality = null;
    private ?int $newWidth = null;
    private ?int $newHeight = null;

    public function __construct(Image $image)
    {
        $this->image = $image;
    }

    public function getSourceImage(): Image
    {
        return $this->image;
    }

    public function getNewType(): ?string
    {
        return $this->newType;
    }

    public function setType(?string $type = null): self
    {
        $this->newType = $this->image->getType() !== $type ? $type : null;

        return $this;
    }

    public function getNewWidth(): ?int
    {
        return $this->newWidth;
    }

    public function setWidth(?int $width = null): self
    {
        $this->newWidth = $width;

        return $this;
    }

    public function setMaxWidth(int $width): self
    {
        if ($width < $this->image->getWidth()) {
            $this->newHeight = $width / ($this->image->getWidth() / $this->image->getHeight());
            $this->newWidth = $width;
        }

        return $this;
    }

    public function getNewHeight(): ?int
    {
        return $this->newHeight;
    }

    public function setHeight(?int $height = null): self
    {
        $this->newHeight = $height;

        return $this;
    }

    public function setMaxHeight(int $height): self
    {
        if ($height < $this->image->getHeight()) {
            $this->newWidth = $height * ($this->image->getWidth() / $this->image->getHeight());
            $this->newHeight = $height;
        }

        return $this;
    }

    public function getQuality(): ?int
    {
        return $this->quality;
    }

    public function setQuality(?int $quality = null): self
    {
        $this->quality = $quality;

        return $this;
    }

    public function isChanged(): bool
    {
        return null !== $this->newType
            || null !== $this->newWidth
            || null !== $this->newHeight
            || null !== $this->quality;
    }
}
