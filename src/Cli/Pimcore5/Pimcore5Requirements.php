<?php
/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Cli\Pimcore5;

use Symfony\Component\Intl\Intl;
use Symfony\Requirements\RequirementCollection;

class Pimcore5Requirements extends RequirementCollection
{
    const REQUIRED_PHP_VERSION = '7.0.0';

    public function __construct()
    {
        $installedPhpVersion = phpversion();

        $this->addRequirement(
            version_compare(phpversion(), static::REQUIRED_PHP_VERSION, '>='),
            sprintf('PHP version must be at least %s (%s installed)', static::REQUIRED_PHP_VERSION, $installedPhpVersion),
            sprintf('You are running PHP version "<strong>%s</strong>", but Pimcore 5 needs at least PHP "<strong>%s</strong>" to run.
                Before using Pimcore 5, upgrade your PHP installation, preferably to the latest version.',
                $installedPhpVersion, static::REQUIRED_PHP_VERSION),
            sprintf('Install PHP %s or newer (installed version is %s)', static::REQUIRED_PHP_VERSION, $installedPhpVersion)
        );

        $this->addRequirement(
            extension_loaded('intl'),
            'intl extension needs to be available',
            'Install and enable the <strong>intl</strong> extension.'
        );

        $this->addIntlRequirements();

        $this->addRequirement(
            function_exists('iconv'),
            'iconv() must be available',
            'Install and enable the <strong>iconv</strong> extension.'
        );

        $this->addRequirement(
            function_exists('json_encode'),
            'json_encode() must be available',
            'Install and enable the <strong>JSON</strong> extension.'
        );

        $this->addRequirement(
            function_exists('session_start'),
            'session_start() must be available',
            'Install and enable the <strong>session</strong> extension.'
        );

        $this->addRequirement(
            function_exists('ctype_alpha'),
            'ctype_alpha() must be available',
            'Install and enable the <strong>ctype</strong> extension.'
        );

        $this->addRequirement(
            function_exists('token_get_all'),
            'token_get_all() must be available',
            'Install and enable the <strong>Tokenizer</strong> extension.'
        );

        $this->addRequirement(
            function_exists('simplexml_import_dom'),
            'simplexml_import_dom() must be available',
            'Install and enable the <strong>SimpleXML</strong> extension.'
        );

        if (function_exists('apc_store') && ini_get('apc.enabled')) {
            if (version_compare($installedPhpVersion, '5.4.0', '>=')) {
                $this->addRequirement(
                    version_compare(phpversion('apc'), '3.1.13', '>='),
                    'APC version must be at least 3.1.13 when using PHP 5.4',
                    'Upgrade your <strong>APC</strong> extension (3.1.13+).'
                );
            } else {
                $this->addRequirement(
                    version_compare(phpversion('apc'), '3.0.17', '>='),
                    'APC version must be at least 3.0.17',
                    'Upgrade your <strong>APC</strong> extension (3.0.17+).'
                );
            }
        }

        $this->addPhpConfigRequirement('detect_unicode', false);

        if (extension_loaded('suhosin')) {
            $this->addPhpConfigRequirement(
                'suhosin.executor.include.whitelist',
                create_function('$cfgValue', 'return false !== stripos($cfgValue, "phar");'),
                false,
                'suhosin.executor.include.whitelist must be configured correctly in php.ini',
                'Add "<strong>phar</strong>" to <strong>suhosin.executor.include.whitelist</strong> in php.ini<a href="#phpini">*</a>.'
            );
        }

        if (extension_loaded('xdebug')) {
            $this->addPhpConfigRequirement(
                'xdebug.show_exception_trace', false, true
            );

            $this->addPhpConfigRequirement(
                'xdebug.scream', false, true
            );

            $this->addPhpConfigRequirement(
                'xdebug.max_nesting_level',
                create_function('$cfgValue', 'return $cfgValue > 100;'),
                true,
                'xdebug.max_nesting_level should be above 100 in php.ini',
                'Set "<strong>xdebug.max_nesting_level</strong>" to e.g. "<strong>250</strong>" in php.ini<a href="#phpini">*</a> to stop Xdebug\'s infinite recursion protection erroneously throwing a fatal error in your project.'
            );
        }

        $pcreVersion = defined('PCRE_VERSION') ? (float) PCRE_VERSION : null;

        $this->addRequirement(
            null !== $pcreVersion,
            'PCRE extension must be available',
            'Install the <strong>PCRE</strong> extension (version 8.0+).'
        );

        if (null !== $pcreVersion) {
            $this->addRecommendation(
                $pcreVersion >= 8.0,
                sprintf('PCRE extension should be at least version 8.0 (%s installed)', $pcreVersion),
                '<strong>PCRE 8.0+</strong> is preconfigured in PHP since 5.3.2 but you are using an outdated version of it. Pimcore probably works anyway but it is recommended to upgrade your PCRE extension.'
            );
        }

        if (extension_loaded('mbstring')) {
            $this->addPhpConfigRequirement(
                'mbstring.func_overload',
                create_function('$cfgValue', 'return (int) $cfgValue === 0;'),
                true,
                'string functions should not be overloaded',
                'Set "<strong>mbstring.func_overload</strong>" to <strong>0</strong> in php.ini<a href="#phpini">*</a> to disable function overloading by the mbstring extension.'
            );
        }

        $this->addRecommendation(
            class_exists('DomDocument'),
            'PHP-DOM and PHP-XML modules should be installed',
            'Install and enable the <strong>PHP-DOM</strong> and the <strong>PHP-XML</strong> modules.'
        );

        $this->addRecommendation(
            function_exists('mb_strlen'),
            'mb_strlen() should be available',
            'Install and enable the <strong>mbstring</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('iconv'),
            'iconv() should be available',
            'Install and enable the <strong>iconv</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('utf8_decode'),
            'utf8_decode() should be available',
            'Install and enable the <strong>XML</strong> extension.'
        );

        $this->addRecommendation(
            function_exists('filter_var'),
            'filter_var() should be available',
            'Install and enable the <strong>filter</strong> extension.'
        );

        if (!defined('PHP_WINDOWS_VERSION_BUILD')) {
            $this->addRecommendation(
                function_exists('posix_isatty'),
                'posix_isatty() should be available',
                'Install and enable the <strong>php_posix</strong> extension (used to colorize the CLI output).'
            );
        }

        $accelerator =
            (extension_loaded('eaccelerator') && ini_get('eaccelerator.enable'))
            ||
            (extension_loaded('apc') && ini_get('apc.enabled'))
            ||
            (extension_loaded('Zend Optimizer+') && ini_get('zend_optimizerplus.enable'))
            ||
            (extension_loaded('Zend OPcache') && ini_get('opcache.enable'))
            ||
            (extension_loaded('xcache') && ini_get('xcache.cacher'))
            ||
            (extension_loaded('wincache') && ini_get('wincache.ocenabled'))
        ;

        $this->addRecommendation(
            $accelerator,
            'a PHP accelerator should be installed',
            'Install and/or enable a <strong>PHP accelerator</strong> (highly recommended).'
        );

        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $this->addRecommendation(
                $this->getRealpathCacheSize() >= 5 * 1024 * 1024,
                'realpath_cache_size should be at least 5M in php.ini',
                'Setting "<strong>realpath_cache_size</strong>" to e.g. "<strong>5242880</strong>" or "<strong>5M</strong>" in php.ini<a href="#phpini">*</a> may improve performance on Windows significantly in some cases.'
            );
        }

        $this->addPhpConfigRequirement('short_open_tag', false);

        $this->addPhpConfigRequirement('magic_quotes_gpc', false, true);

        $this->addPhpConfigRequirement('register_globals', false, true);

        $this->addPhpConfigRequirement('session.auto_start', false);

        $this->addRecommendation(
            class_exists('PDO'),
            'PDO should be installed',
            'Install <strong>PDO</strong> (mandatory for Doctrine).'
        );

        if (class_exists('PDO')) {
            $drivers = \PDO::getAvailableDrivers();
            $this->addRecommendation(
                count($drivers) > 0,
                sprintf('PDO should have some drivers installed (currently available: %s)', count($drivers) ? implode(', ', $drivers) : 'none'),
                'Install <strong>PDO drivers</strong> (mandatory for Doctrine).'
            );
        }
    }

    protected function addIntlRequirements()
    {
        // in some WAMP server installations, new Collator() returns null
        $this->addRecommendation(
            null !== new \Collator('fr_FR'),
            'intl extension should be correctly configured',
            'The intl extension does not behave properly. This problem is typical on PHP 5.3.X x64 WIN builds.'
        );

        // check for compatible ICU versions (only done when you have the intl extension)
        if (defined('INTL_ICU_VERSION')) {
            $version = INTL_ICU_VERSION;
        } else {
            $reflector = new \ReflectionExtension('intl');

            ob_start();
            $reflector->info();
            $output = strip_tags(ob_get_clean());

            preg_match('/^ICU version +(?:=> )?(.*)$/m', $output, $matches);
            $version = $matches[1];
        }

        $this->addRecommendation(
            version_compare($version, '4.0', '>='),
            'intl ICU version should be at least 4+',
            'Upgrade your <strong>intl</strong> extension with a newer ICU version (4+).'
        );

        /*
        $this->addRecommendation(
            Intl::getIcuDataVersion() <= Intl::getIcuVersion(),
            sprintf('intl ICU version installed on your system is outdated (%s) and does not match the ICU data bundled with Symfony (%s)', Intl::getIcuVersion(), Intl::getIcuDataVersion()),
            'To get the latest internationalization data upgrade the ICU system package and the intl PHP extension.'
        );

        if (Intl::getIcuDataVersion() <= Intl::getIcuVersion()) {
            $this->addRecommendation(
                Intl::getIcuDataVersion() === Intl::getIcuVersion(),
                sprintf('intl ICU version installed on your system (%s) does not match the ICU data bundled with Symfony (%s)', Intl::getIcuVersion(), Intl::getIcuDataVersion()),
                'To avoid internationalization data inconsistencies upgrade the symfony/intl component.'
            );
        }
        */

        $this->addPhpConfigRecommendation(
            'intl.error_level',
            create_function('$cfgValue', 'return (int) $cfgValue === 0;'),
            true,
            'intl.error_level should be 0 in php.ini',
            'Set "<strong>intl.error_level</strong>" to "<strong>0</strong>" in php.ini<a href="#phpini">*</a> to inhibit the messages when an error occurs in ICU functions.'
        );
    }

    /**
     * Loads realpath_cache_size from php.ini and converts it to int.
     *
     * (e.g. 16k is converted to 16384 int)
     *
     * @return int
     */
    protected function getRealpathCacheSize()
    {
        $size = ini_get('realpath_cache_size');
        $size = trim($size);
        $unit = '';
        if (!ctype_digit($size)) {
            $unit = strtolower(substr($size, -1, 1));
            $size = (int) substr($size, 0, -1);
        }
        switch ($unit) {
            case 'g':
                return $size * 1024 * 1024 * 1024;
            case 'm':
                return $size * 1024 * 1024;
            case 'k':
                return $size * 1024;
            default:
                return (int) $size;
        }
    }
}
