# Pimcore CLI Tools

[![Build Status](https://travis-ci.org/pimcore/pimcore-cli.svg?branch=master)](https://travis-ci.org/pimcore/pimcore-cli)

A collection of command line tools for managing Pimcore installations.

## Documentation

* [Pimcore 5 Migration](./doc/pimcore_5_migration.md)

## Shell completion

The package is using [stecman/symfony-console-completion](https://github.com/stecman/symfony-console-completion) to provide
shell completion. To have your shell complete commands, arguments and options, you need to run the following in your
shell:

```
# BASH ~4.x, ZSH
source <(pimcore.phar _completion --generate-hook)

# BASH ~3.x, ZSH
pimcore.phar _completion --generate-hook | source /dev/stdin

# BASH (any version)
eval $(pimcore.phar _completion --generate-hook)
```
