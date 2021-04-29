<?php

namespace Ambta\DoctrineEncryptBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Configuration tree for security bundle. Full tree you can see in Resources/docs
 *
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{

    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        // Create tree builder
        $treeBuilder = new TreeBuilder('ambta_doctrine_encrypt');
        if (\method_exists($treeBuilder, 'getRootNode')) {
            $rootNode = $treeBuilder->getRootNode();
        } else {
            // BC layer for symfony/config 4.1 and older
            $rootNode = $treeBuilder->root('ambta_doctrine_encrypt');
        }

        // Grammar of config tree
        $rootNode
                ->children()
                    ->scalarNode('encryptor_class')
                        ->defaultValue('Halite')
                    ->end()
                    ->scalarNode('secret_directory_path')
                        ->defaultValue('%kernel.project_dir%')
                    ->end()
                    ->scalarNode('secret_key_name')
                        ->defaultValue('')
                    ->end()
                ->end();

        return $treeBuilder;
    }

}
