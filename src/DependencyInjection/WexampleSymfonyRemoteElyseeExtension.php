<?php

namespace Wexample\SymfonyRemoteElysee\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

class WexampleSymfonyRemoteElyseeExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        foreach (['cache_dir', 'user_agent', 'base_url', 'listings', 'prefixes', 'delay', 'attempts', 'retry_delay'] as $key) {
            $container->setParameter('wexample_symfony_remote_elysee.'.$key, $config[$key]);
        }

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
