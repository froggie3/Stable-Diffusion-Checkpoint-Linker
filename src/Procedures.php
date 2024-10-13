<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class Procedures
{
    public array $link, $unlink;

    public function __construct()
    {
        $this->link = [];
        $this->unlink = [];
    }
}
