<?php

declare(strict_types=1);

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

namespace Pimcore\Config\System;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class SmtpNodeConfiguration implements ConfigurationInterface
{
    /**
     * @inheritDoc
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder();

        $rootNode = $treeBuilder->root('smtp');
        $rootNode
            ->addDefaultsIfNotSet()
            ->beforeNormalization()
            ->ifTrue(function ($v) {
                return is_array($v) && array_key_exists('name', $v);
            })
            ->then(function ($v) {
                @trigger_error('The SMTP configuration "name" does is not used anymore', E_USER_DEPRECATED);
                unset($v['name']);

                return $v;
            })
            ->end()
            ->children()
                ->scalarNode('host')
                    ->defaultValue('')
                ->end()
                ->scalarNode('port')
                    ->defaultValue('')
                ->end()
                ->scalarNode('name')->end()
                ->scalarNode('ssl')
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifEmpty()
                        ->then(function ($v) {
                            return null;
                        })
                    ->end()
                ->end()
                ->arrayNode('auth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('method')
                            ->defaultNull()
                            ->beforeNormalization()
                                ->ifEmpty()
                                ->then(function ($v) {
                                    return null;
                                })
                            ->end()
                        ->end()
                        ->scalarNode('username')
                            ->defaultValue('')
                        ->end()
                        ->scalarNode('password')
                            ->defaultValue('')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
