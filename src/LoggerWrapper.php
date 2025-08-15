<?php

declare(strict_types=1);

namespace Iigau\StableDiffusionCheckpointLinker;

use Monolog\Logger;

class LoggerWrapper
{
    private string $prefix;

    public function __construct(private Logger $logger, string|null $prefix = null)
    {
        $this->prefix = $this->addFormattedPrefix($prefix);
    }

    private function addFormattedPrefix(string|null $prefix): string
    {
        return is_null($prefix) ? "" : "[$prefix] ";
    }

    public function debug(string $message, array $context)
    {
        $this->logger->debug("{$this->prefix}$message", $context);
    }

    public function error(string $message, array $context)
    {
        $this->logger->error("{$this->prefix}$message", $context);
    }

    public function warning(string $message, array $context)
    {
        $this->logger->warning("{$this->prefix}$message", $context);
    }
}
