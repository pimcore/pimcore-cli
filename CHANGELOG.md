Changelog
=========

0.7.0
-----

Added possibility to exclude fixers by their name by passing the `--exclude-fixer` option. Fixer names can be listed with
the `--list-fixers` option.

0.6.0
-----

* Updated to PHP-CS-Fixer 2.6 and changed calls to work with immutable tokens

Added controller fixers which are able to:

* Add an `AppBundle\Controller` controller namespace 
* Change the controller parent class to `FrontendController`
* Add a `Request $request` argument to controller actions
* Change calls from `$this->getParam()` to `$request->get()`

0.5.0
-----

* Added `self-update` command

0.4.0
-----

* Move files by default and add pimcore5:views:update-db-references command

0.3.1
-----

* Call pimcore5:config:fix implicitely when migrating filesystem

0.3.0
-----

* Add pimcore5:config:fix command

0.2.0
-----

* Refine command names
* Add shell completion

0.1.0
-----

* Bootstrap application
* Implement `migration.sh` as command and add additional Pimcore 5 related commands
