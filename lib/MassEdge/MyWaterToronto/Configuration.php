<?php

namespace MassEdge\MyWaterToronto;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder('mywatertoronto');

        $treeBuilder->getRootNode()
            ->children()
                ->node('account_number', 'scalar')
                    ->cannotBeEmpty()
                ->end()
                ->node('client_number', 'scalar')
                    ->cannotBeEmpty()
                ->end()
                ->node('last_name', 'scalar')
                    ->cannotBeEmpty()
                ->end()
                ->node('postal_code', 'scalar')
                    ->cannotBeEmpty()
                ->end()
                ->node('most_recent_method_payment', 'scalar')
                    ->cannotBeEmpty()
                ->end()
            ->end();

        // ... add node definitions to the root of the tree
        // $treeBuilder->getRootNode()->...

        return $treeBuilder;
    }
}
