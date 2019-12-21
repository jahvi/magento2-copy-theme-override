# Magento 2 Copy Theme Override Command
[![Build Status](https://travis-ci.org/jahvi/magento2-copy-theme-override.svg?branch=master)](https://travis-ci.org/jahvi/magento2-copy-theme-override)
[![Coverage Status](https://coveralls.io/repos/github/jahvi/magento2-copy-theme-override/badge.svg?branch=master)](https://coveralls.io/github/jahvi/magento2-copy-theme-override?branch=master)

🎩 Magento 2 command to automatically copy files into active theme.

![screen2](https://user-images.githubusercontent.com/661330/43463240-008b4706-94d0-11e8-8abe-9516c963ec13.gif)

## Installation

1. Install via composer `composer require jahvi/magento2-copy-theme-override --dev`
2. Run `php bin/magento setup:upgrade`

## How it works?

After installing the extension you will have access to a new command:

```shell
php bin/magento dev:copy-theme-override $file_path
```

This takes a single `$file_path` argument which is the absolute path of the CSS, LESS, JS, PHTML or HTML file you want to override in your theme, so for example to override the `product/list.phtml` template you'd run:

```shell
php bin/magento dev:copy-theme-override /var/www/magento2/vendor/magento/module-catalog/view/frontend/templates/product/list.phtml
```

And it will copy the file into your theme as:

```shell
/var/www/magento2/vendor/magento/app/design/Sample/theme/Magento_Catalog/templates/product/list.phtml
```

## Setting up your IDE

By itself the command is not very useful but most IDEs or code editors provide a way to run custom commands using keyboard shortcuts that will help simplify this workflow, below are some examples of a few popular ones.

### VS Code

1. Create a `.vscode/tasks.json` file in the project root with the following content:

```json
{
    "version": "2.0.0",
    "tasks": [
        {
            "label": "Copy Theme Override",
            "type": "shell",
            "command": "php bin/magento dev:copy-theme-override ${file}",
            "group": {
                "kind": "build",
                "isDefault": true
            }
        }
    ]
}
```

2. Open the file to override.
3. Run build task shortcut, by default `Shift + Cmd + B` or `Ctrl + Shift + B` on Windows/Linux.

### Sublime Text

1. Create new build system `Tools > Build System > New Build System...` with the following content:

```json
{
    "shell_cmd": "php bin/magento dev:copy-theme-override $file",
    "working_dir": "$folder"
}
```

2. Open the file to override.
3. Run Build task, by default `Cmd + B` or `Ctrl + B` on Windows/Linux.

