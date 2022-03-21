<?php

namespace Alhames\ImageBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class AlhamesImageBundle extends Bundle
{
    public function getPath(): string
    {
        if (null === $this->path) {
            $this->path = \dirname(__DIR__);
        }

        return $this->path;
    }
}
