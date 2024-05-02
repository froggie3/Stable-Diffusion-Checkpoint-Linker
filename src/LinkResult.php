<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

enum LinkResult {
    case NOT_LINKED;
    case NEWLY_LINKED;
    case ALREADY_LINKED;
}