<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

class UnlinkerSimulator implements UnlinkerInterface
{
    private LoggerWrapper $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = new LoggerWrapper($logger, "simulating");
    }

    public function unlink(string $path): bool
    {
        $this->logger->debug("successfully unlinked", ['path' => $path]);
        return true;
    }
}
