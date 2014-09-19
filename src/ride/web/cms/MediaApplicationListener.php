<?php

namespace ride\web\cms;

use ride\library\event\Event;

use ride\web\base\menu\MenuItem;

class MediaApplicationListener {

    public function prepareContentMenu(Event $event) {
        $menuItem = new MenuItem();
        $menuItem->setTranslation('title.media');
        $menuItem->setRoute('media.overview');

        $menu = $event->getArgument('menu');
        $menu->addMenuItem($menuItem);
    }

}
