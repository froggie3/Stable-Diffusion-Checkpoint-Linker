<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

final class Procedures
{
    public ProceduresArray $link, $unlink;

    public function __construct()
    {
        $this->link = new ProceduresArray();
        $this->unlink = new ProceduresArray();
    }
}
