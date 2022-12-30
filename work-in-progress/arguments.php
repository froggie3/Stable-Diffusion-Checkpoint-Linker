#!/usr/bin/php
<?php

declare (strict_types = 1);

if ($argc != 2 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
?>

This is a command line tool to make hardlinks for the requirements
of Stable Diffusion Webui, such as *.ckpt, *.pt, *.safetensors, and etc. 

  Usage:
  <?php echo $argv[0]; ?> <option>

  -h, --help    show this help message and exit
  --unlink      delete all the hardlinks

<?php
} else {
    echo $argv[1];
}
