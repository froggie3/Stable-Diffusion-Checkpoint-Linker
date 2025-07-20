<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Iigau\StableDiffusionCheckpointLinker\Linker;
use Iigau\StableDiffusionCheckpointLinker\Unlinker;
use Iigau\StableDiffusionCheckpointLinker\Operations;
use Iigau\StableDiffusionCheckpointLinker\SQLiteRecordRepository;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

$config = Spyc::YAMLLoad(file_get_contents("php://stdin"));
$operations = new Operations($config);

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
