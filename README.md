# SD-Linker

![images/20230406010745.png](images/20230406014524.png)

Using this utility enables you to make a hardlink or a symlink of various weight
file used in Stable Diffusion Web UI (`*.safetensors`, `*.ckpt`, `*.pt`, or
something like that) without copying from the original folder.  
Since this will just make no real copy of file, making links or its deletion is really fast.

This utility should be run in PHP environment installed.

<https://www.php.net/>

## How to use

To make a hardlink or symbolic link, run the PowerShell script with `run.ps1`.   
Note that you need to make configuration in advance if the configuration is not
ready! (if so you can refer to `Making a template for config file`

The utility will automaticaly escalate the program and try to start with
administrative privileges.

```plain
./run.ps1
```

or you can launch the program without opening terminal, it's really useful!

![images/20230406010001.png](./images/20230406010001.png)

Then prompt will ask whether you want to open the config file,
you can answer `y`, `n`, or just pressing enter (in this case it is equivalent
as you answer `y`)

```plain
Do you want to open notepad to edit the configuration? [Y/n]: y
```

Edit your config file with the editor you preferred, close it as you finished
― That's it!

![images/20230406010745.png](./images/20230406010745.png)

### Making a template for config file

You can easily prepare your new configuration by this command below
(btw my apologies for bad naming for a subcommand)

Executing this command, for example, will make new configuration as `foo.json`.

```
php src/main.php newconfig foo.json
```

## Setting

Open `config/config.json` and set the installation directory for Stable
Diffusion Web UI.

Config file is roughly separated into two sections: `desination` and `source`.
In `destination` you can specify the destination for links to weights to be
actually placed, while `source` is for the location for weight are located.

`source` section is also devided into some sections so you can tell the type
of weight. Types are like: `checkpoint`, `vae`, `embeddings`, `hypernetworks`,
`lora`.

Schema

```json
{
  "destination": {
    "webui": "",
    "checkpoint": "",
    "vae": "",
    "embeddings": "",
    "hypernetworks": "",
    "lora": ""
  },
  "source": {
    "checkpoint": [
      {
        "meta": {
          // you can leave some comments on each entry
          "comment": "",
          // setting this to false all the models in entry 'weightList' will be unlinked!
          "enabled": true
        },
        // set your base directory for the weights in 'weightsList'
        "baseDirectory": "C:/foo",
        "weightsList": [
          // just write filename!
          "foo.safetensors"
        ],
        // moving models on 'ignoreList' also allows to set model status one by one.
        // it is not a mandatry entry: you can omit!
        "ignoreList": [
          // just write filename!
          "something.ckpt"
        ]
      }
    ],
    "vae": [
      // same as above
    ],
    "embeddings": [
      // same as above
    ],
    "hypernetworks": [
      // same as above
    ],
    "lora": [
      // same as above
    ]
  }
}
```

## Misc

### Environments

```plain
> php --version
PHP 8.2.3 (cli) (built: Feb 14 2023 09:54:05) (NTS Visual C++ 2019 x64)
Copyright (c) The PHP Group
Zend Engine v4.2.3, Copyright (c) Zend Technologies
```

### Todo

- Provide the ability to change the name of the symbolic destination

### References

- <https://github.com/AUTOMATIC1111/stable-diffusion-webui>
