<?php

namespace SOTB\CoreBundle\Menu;

use Knp\Menu\FactoryInterface;
use Symfony\Component\DependencyInjection\ContainerAware;

/**
 * @author Matt Drollette <matt@drollette.com>
 */
class Builder extends ContainerAware
{
    public function mainMenu(FactoryInterface $factory, array $options)
    {
        $menu = $factory->createItem('root');

        $menu->setChildrenAttribute('class', 'nav');

        $menu->addChild('Home', array('route' => 'homepage'));
        $menu->addChild('Torrents', array('route' => 'torrent_list'));
        $menu->addChild('Request', array('route' => 'torrent_upload'));

        return $menu;
    }
}