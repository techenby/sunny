<?php

namespace App\Ui;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Edge\Elements\TopBarAction;
use Native\Mobile\Edge\Layouts\Builders\NavAction;

class Menu
{
    public static function item(string $ref, string $label, Ios $ios, Android $android, string $press, ?string $a11yLabel = null): TopBarAction
    {
        $action = NavAction::make($ref)
            ->label($label)
            ->icon(ios: $ios->value, android: $android->value)
            ->press($press);

        if ($a11yLabel !== null) {
            $action->a11yLabel($a11yLabel);
        }

        return $action->toElement()->ref($ref)->key($ref);
    }

    public static function delete(string $ref, string $label, string $press, ?string $a11yLabel = null): TopBarAction
    {
        $action = NavAction::make($ref)
            ->label($label)
            ->icon(ios: Ios::Trash->value, android: Android::Delete->value)
            ->destructive()
            ->press($press);

        if ($a11yLabel !== null) {
            $action->a11yLabel($a11yLabel);
        }

        return $action->toElement()->ref($ref)->key($ref);
    }
}
