# Pimcore 5 Migration scripts

The Pimcore CLI ships with commands which ease the migration to Pimcore 5.

> Before running any migration tasks please make sure you have a proper backup!

| Command                               | Description |
|---------------------------------------|-------------|
| `pimcore5:check-requirements`         | Checks if your environment matches the requirements for Pimcore 5. |
| `pimcore5:migrate:filesystem`         | Migrates your filesystem to Pimcore 5. Unpacks ZIP and moves stuff into place. |
| `pimcore5:config:fix`                 | Updates `system.php` to match Pimcore 5 requirements. |
| `pimcore5:controllers:process`        | Rewrites controllers with common changes (e.g. adds a `Request $request` parameter to actions). Path to the controllers folder must be passed. |
| `pimcore5:views:rename`               | Migrates views to new naming conventions for PHP templating engine (changes extension from `.php` to `.html.php` and changes filenames from dashed-case to camelCase). |
| `pimcore5:views:update-db-references` | Updates DB references to view files (updates documents setting a custom template). |
| `pimcore5:views:process`              | Rewrites templates with common changes needed for Pimcore 5 templating (e.g. changes `setLayout()` to `extend()`). |
| `pimcore5:migrate:areabrick`          | Migrates a Pimcore 4 areabrick (XML format) to Pimcore 5 format (Areabrick class). |

A typical migration scenario could look like the following. Several commands make assumptions regarding file naming which
may not fit your needs. Please check what has been done and revert what you don't need. This may get more flexible/configurable
in the future.

To introspect what is done by the commands you can use the following options:

* Every command comes with a `--dry-run` option which lets you inspect what the command would do
* The `process` commands support a `--diff` option which can be used to display the processed changes
* Commands doing filesystem operations support a `--collect-commands` option which can either be set to `shell` or to `git`.
  If the option is passed, a list of filesystem operations will be printed with the selected format. `git` will output the
  commands as git commands if applicable (e.g. a `mv` would be printed as `git mv`). 

<!-- please do not remove the spacer below as it is used from the help:pimcore5:migration-cheatsheet command -->
---

```
# assuming pimcore.phar is on your $PATH
$ cd <path-to-installation>

# migrate filesystem
$ pimcore.phar pimcore5:migrate:filesystem . ../pimcore-unstable.zip

# the config:fix command could update the system.php to match pimcore 5 requirements but
# there is no need to call it after a filesystem migration as it is implicitely called
$ pimcore.phar pimcore5:config:fix var/config/system.php

# generate an AppBundle via PimcoreGeneratorBundle
$ bin/console pimcore:generate:bundle --namespace AppBundle

# controller files need to be moved manually, but there is no name changing involved as with views
$ rm src/AppBundle/Controller/* && mv legacy/website/controllers/* src/AppBundle/Controller

# process controllers (make sure you check what has been changed!)
$ pimcore.phar pimcore5:controllers:process src/AppBundle/Controller

# rename view scripts (pass -c option to copy files instead of moving them)
$ pimcore.phar pimcore5:views:rename legacy/website/views/scripts app/Resources/views

# rename layouts - there is no dedicated layouts directory anymore, you can put the layout wherever you want
$ pimcore.phar pimcore5:views:rename legacy/website/views/layouts app/Resources/views

# migrate a legacy areabrick to new format (make sure to read any warnings returned by the command!)
$ pimcore.phar pimcore5:migrate:areabrick legacy/website/views/areas/gallery/area.xml app src

# migrate all areabricks in a loop
for i in legacy/website/views/areas/*; do
    pimcore.phar pimcore5:migrate:areabrick -v $i/area.xml app src
done

# process views (make sure you check what has been changed!). this command can be applied to any view directory (e.g. views
# inside a bundle)
$ pimcore.phar pimcore5:views:process app/Resources/views

# update db view references (documents setting a custom template) to match new camelCased naming scheme
$ pimcore.phar pimcore5:views:update-db-references
```
