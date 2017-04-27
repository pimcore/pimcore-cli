# Pimcore 5 Migration scripts

The Pimcore CLI ships with a couple of scripts which ease the migration to Pimcore 5.

> Before running any migration tasks please make sure you have a proper backup!

| Command | Description |
|---------|-------------|
| `pimcore5:check-requirements` | Checks if your environment matches the requirements for Pimcore 5. |
| `pimcore5:migrate:filesystem` | Migrates your filesystem to Pimcore 5. Unpacks ZIP and moves stuff into place |
| `pimcore5:migrate:views` | Migrates views to new naming conventions for PHP templating engine (changes extension from `.php` to `.html.php` and changes filenames from dashed-case to camelCase |
| `pimcore5:fix-views` | Rewrites templates with common changes needed for Pimcore 5 templating (e.g. changes `setLayout()` to `extend()`) |
| `pimcore5:migrate:areabrick` | Migrates a Pimcore 4 areabrick (XML format) to Pimcore 5 format (Areabrick class) |

A typical migration scenario could look like the following. The  `migrate:views` and `fix-views` commands make a couple
of assumptions regarding file naming which may not fit your needs. Please check what has been done and revert what you
don't need. This may get more flexible/configurable in the future.

```
# assuming pimcore.phar is on your $PATH
$ cd <path-to-installation>

# migrate filesystem
$ pimcore.phar pimcore5:migrate-filesystem . ../pimcore-unstable.zip

# migrate view scripts (pass -m option to move files instead of copying them)
$ pimcore.phar pimcore5:migrate:views legacy/website/views/scripts app/Resources/views

# migrate layouts
$ pimcore.phar pimcore5:migrate:views legacy/website/views/layouts app/Resources/views

# fix views (make sure you check what has been changed!)
$ pimcore.phar pimcore5:fix-views app/Resources/views

# migrate a legacy areabrick to new format (make sure to read any warnings returned by the command)
$ pimcore.phar pimcore5:migrate:areabrick legacy/website/views/areas/gallery/area.xml app src
```
