<?php

namespace Wexample\SymfonyRemoteElysee\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyRemoteElysee\Service\ElyseeClient;
use Wexample\SymfonyRemoteElysee\Service\ElyseePageParser;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_remote_elysee');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('cache_dir')
                    ->defaultValue('%kernel.project_dir%/var/remote/elysee')
                    ->info('Where the publications read are kept: out of the kernel\'s cache, which a deployment empties.')
                ->end()
                ->scalarNode('user_agent')
                    ->defaultValue('wexample-symfony-remote-elysee/1.0')
                    ->info('Who asks. Name the app.')
                ->end()
                ->scalarNode('base_url')->defaultValue(ElyseeClient::DEFAULT_BASE_URL)->end()
                ->arrayNode('listings')
                    ->info('The pages listing the latest publications, past the base address.')
                    ->scalarPrototype()->end()
                    ->defaultValue(ElyseeClient::DEFAULT_LISTINGS)
                ->end()
                ->arrayNode('prefixes')
                    ->info('The presidents\' sections of the site: their publications are /<prefix>/YYYY/MM/DD/<slug>, their dossiers /<prefix>/<slug>.')
                    ->scalarPrototype()->end()
                    ->defaultValue(ElyseePageParser::DEFAULT_PREFIXES)
                ->end()
                ->integerNode('delay')->defaultValue(1000)->min(0)->info('Milliseconds between two requests.')->end()
                ->integerNode('attempts')->defaultValue(3)->min(1)->end()
                ->integerNode('retry_delay')->defaultValue(5)->min(0)->info('Seconds before the next attempt, times the attempt number.')->end()
            ->end();

        return $treeBuilder;
    }
}
