<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iigau\StableDiffusionCheckpointLinker\Linker;
use Iigau\StableDiffusionCheckpointLinker\Unlinker;
use Iigau\StableDiffusionCheckpointLinker\Operations;
use Iigau\StableDiffusionCheckpointLinker\Counter;
use Iigau\StableDiffusionCheckpointLinker\SQLiteRecordRepository;
use Iigau\StableDiffusionCheckpointLinker\Config\ConfigurationLoader;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

try {
    $input = file_get_contents('php://stdin');
    if ($input === false) {
        throw new InvalidArgumentException('Failed to read configuration from stdin.');
    }
    $config = (new ConfigurationLoader())->load($input);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, 'Configuration error: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}

$stream = new StreamHandler("php://stderr", Level::Info);
$output = "%message% %context% %extra%\n";
$formatter = new \Monolog\Formatter\LineFormatter(
    $output,
    null,
    true, // allow line breaks
    true  // ignore empty context and extra
);
$stream->setFormatter($formatter);
$logger = new Logger("Syncronizer", [$stream]);
$logger->pushHandler($stream);

$repo = new SQLiteRecordRepository(__DIR__ . '/../data/links.sqlite');

$linker = new Linker($logger, $repo);
$unlinker = new Unlinker($logger, $repo);

$counter = new Counter();

$operations = new Operations(
    $config['lookupKeys'],
    $config['source'],
    $config['destination'],
    $linker,
    $unlinker,
    $counter,
    $logger
);
