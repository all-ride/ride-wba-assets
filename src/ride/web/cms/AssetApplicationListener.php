<?php

namespace ride\web\cms;

use ride\library\event\Event;

use ride\web\base\menu\MenuItem;

class AssetApplicationListener {

    public function prepareContentMenu(Event $event) {
        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.assets');
        $menuItem->setRoute('assets.overview', array('type' => 'actor'));

        $menu = $event->getArgument('menu');
        $menu->addMenuItem($menuItem);

        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.assets');
        $menuItem->setRoute('assets.overview', array('type' => 'collection'));

        $menu = $event->getArgument('menu');
        $menu->addMenuItem($menuItem);
    }
}
