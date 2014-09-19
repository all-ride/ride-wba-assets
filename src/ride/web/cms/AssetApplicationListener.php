<?php

namespace ride\web\cms;

use ride\library\event\Event;

use ride\web\base\menu\MenuItem;

class AssetApplicationListener {

    public function prepareContentMenu(Event $event) {
        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.asset');
        $menuItem->setRoute('asset.overview');

        $menu = $event->getArgument('menu');
        $menu->addMenuItem($menuItem);
    }
}
