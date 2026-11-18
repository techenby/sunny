<?php

namespace App\Support;

use App\Icons\Android;
use App\Icons\Ios;
use Native\Mobile\Facades\Dialog;
use Victorycodedev\ToastKit\Events\ToastActionPressed;
use Victorycodedev\ToastKit\PendingToast;
use Victorycodedev\ToastKit\ToastKit;

class Toast
{
    public const ACTION_PRESSED = ToastActionPressed::class;

    public const SHORT = 4000;

    public const LONG = 8000;

    private const SUCCESS = ['light' => '#16A34A', 'dark' => '#22C55E'];

    public static function success(string $message, ?string $title = null, ?ToastAction $action = null, ?bool $long = null): void
    {
        self::show('success', $message, $title, $action, $long ?? $action !== null);
    }

    public static function info(string $message, ?string $title = null, ?ToastAction $action = null, ?bool $long = null): void
    {
        self::show('info', $message, $title, $action, $long ?? $action !== null);
    }

    public static function error(string $message, ?string $title = null, ?ToastAction $action = null, ?bool $long = null): void
    {
        self::show('error', $message, $title, $action, $long ?? true);
    }

    private static function show(string $variant, string $message, ?string $title, ?ToastAction $action, bool $long): void
    {
        if (! app()->bound(ToastKit::class) || ! function_exists('nativephp_can') || ! nativephp_can('ToastKit.Show')) {
            self::fallback($message, $title, $long);

            return;
        }

        rescue(
            fn () => self::make($variant, $message, $title, $action, $long)->show(),
            fn () => self::fallback($message, $title, $long),
        );
    }

    private static function make(string $variant, string $message, ?string $title, ?ToastAction $action, bool $long): PendingToast
    {
        $dark = isDark();

        $toast = app(ToastKit::class)->make($message)
            ->variant($variant)
            ->title($title)
            ->unique(trim($title."\n".$message))
            ->icon(...self::icon($variant))
            ->position(isAndroid() ? 'bottom' : 'top')
            ->animation('slide')
            ->duration($long ? self::LONG : self::SHORT)
            ->background(theme($dark ? 'surface-variant' : 'surface'))
            ->foreground(theme('on-surface'))
            ->iconColor(self::iconColor($variant, $dark))
            ->actionColor(theme('primary-text'))
            ->cornerRadius(18)
            ->titleText(font: 'semibold')
            ->text(font: 'medium');

        if ($action !== null) {
            $toast->action($action->label, $action->id);

            if ($action->data !== []) {
                $toast->data($action->data);
            }
        }

        return $toast;
    }

    /**
     * @return array{ios: string, android: string}
     */
    private static function icon(string $variant): array
    {
        return match ($variant) {
            'success' => ['ios' => Ios::CheckmarkCircleFill->value, 'android' => Android::CheckCircle->value],
            'error' => ['ios' => Ios::ExclamationmarkCircleFill->value, 'android' => Android::Error->value],
            default => ['ios' => Ios::InfoCircleFill->value, 'android' => Android::Info->value],
        };
    }

    private static function iconColor(string $variant, bool $dark): string
    {
        return match ($variant) {
            'success' => self::SUCCESS[$dark ? 'dark' : 'light'],
            'error' => theme('destructive'),
            default => theme('on-surface-variant'),
        };
    }

    private static function fallback(string $message, ?string $title, bool $long): void
    {
        Dialog::toast(trim($title."\n".$message), $long ? 'long' : 'short');
    }
}
