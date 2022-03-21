<?php

namespace Alhames\ImageBundle;

class ImageException extends \RuntimeException
{
    public const ERR_SIZE = 1;
    public const ERR_TYPE = 2;
    public const ERR_PATH = 3;
    public const ERR_READ = 4;
    public const ERR_WRITE = 5;
    public const ERR_RESOLUTION = 6;
}
