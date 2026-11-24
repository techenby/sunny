<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Native\Mobile\Providers\BrowserServiceProvider;
use Native\Mobile\Providers\CameraServiceProvider;
use Native\Mobile\Providers\NetworkServiceProvider;
use Native\Mobile\Providers\ScannerServiceProvider;
use Native\Mobile\Providers\SecureStorageServiceProvider;
use Native\Mobile\Providers\ShareServiceProvider;
use Native\Mobile\UI\NativeUIServiceProvider;
use NativePHP\BackgroundTasks\BackgroundTasksServiceProvider;
use NativePHP\Clipboard\ClipboardServiceProvider;
use NativePHP\LocalNotifications\LocalNotificationsServiceProvider;
use Nativephp\MobileOta\OtaServiceProvider;
use SRWieZ\NativePHP\Mobile\Screen\ScreenServiceProvider;
use Sunny\ItemScanner\ItemScannerServiceProvider;
use Victorycodedev\ToastKit\ToastKitServiceProvider;
use Vos\LiveActivities\LiveActivitiesServiceProvider;
use ZXN\NativeWatch\Mobile\MobileServiceProvider;

class NativeServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }

    /**
     * The NativePHP plugins to enable.
     *
     * Only plugins listed here will be compiled into your native builds.
     * This is a security measure to prevent transitive dependencies from
     * automatically registering plugins without your explicit consent.
     *
     * @return array<int, class-string<ServiceProvider>>
     */
    public function plugins(): array
    {
        return [
            NativeUIServiceProvider::class,
            BrowserServiceProvider::class,
            CameraServiceProvider::class,
            SecureStorageServiceProvider::class,
            BackgroundTasksServiceProvider::class,
            ItemScannerServiceProvider::class,
            ScannerServiceProvider::class,
            ShareServiceProvider::class,
            ClipboardServiceProvider::class,
            OtaServiceProvider::class,
            MobileServiceProvider::class,
            ScreenServiceProvider::class,
            LocalNotificationsServiceProvider::class,
            LiveActivitiesServiceProvider::class,
            ToastKitServiceProvider::class,
            NetworkServiceProvider::class,
        ];
    }
}
