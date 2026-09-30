<?php

namespace App\Layouts;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Layouts\Builders\Tab;
use Native\Mobile\Edge\Layouts\Builders\TabBar;
use Native\Mobile\Edge\Layouts\NativeLayout;
use Native\Mobile\Edge\NativeComponent;

class TabsLayout extends NativeLayout
{
    public function tabBar(NativeComponent $screen): ?TabBar
    {
        return TabBar::make()
            ->activeColor(theme('primary'))
            ->add(Tab::link('Summary', '/dashboard', ios: Ios::House, android: Android::Home))
            ->add(Tab::link('Inventory', '/inventory', ios: Ios::Archivebox, android: Android::Inventory2))
            ->add(Tab::link('Recipes', '/recipes', ios: Ios::ForkKnife, android: Android::Restaurant));
    }

    public function usesNativeChrome(): bool
    {
        return true;
    }
}
