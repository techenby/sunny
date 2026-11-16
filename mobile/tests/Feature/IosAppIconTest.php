<?php

use Illuminate\Support\Facades\File;
use Native\Mobile\Concerns\InstallsAppIcon;

beforeEach(function () {
    $this->originalBasePath = base_path();
    $this->basePath = sys_get_temp_dir().'/sunny-ios-icon-'.uniqid();

    File::ensureDirectoryExists($this->basePath.'/nativephp/ios/NativePHP');
    File::ensureDirectoryExists($this->basePath.'/nativephp/ios/NativePHP.xcodeproj');
    File::copy(
        $this->originalBasePath.'/vendor/nativephp/mobile/resources/xcode/NativePHP.xcodeproj/project.pbxproj',
        $this->basePath.'/nativephp/ios/NativePHP.xcodeproj/project.pbxproj',
    );
    File::copyDirectory(
        $this->originalBasePath.'/'.config('nativephp.ios_icon'),
        $this->basePath.'/'.config('nativephp.ios_icon'),
    );

    $this->app->setBasePath($this->basePath);

    $this->installer = new class
    {
        use InstallsAppIcon;
    };
});

afterEach(function () {
    $this->app->setBasePath($this->originalBasePath);
    File::deleteDirectory($this->basePath);
});

function appIconNames(): array
{
    preg_match_all(
        '/ASSETCATALOG_COMPILER_APPICON_NAME = ([^;]*);/',
        File::get(base_path('nativephp/ios/NativePHP.xcodeproj/project.pbxproj')),
        $matches,
    );

    return $matches[1];
}

it('points at the committed Icon Composer file', function () {
    expect(config('nativephp.ios_icon'))->toBe('public/images/sunny.icon')
        ->and($this->originalBasePath.'/public/images/sunny.icon/icon.json')->toBeFile()
        ->and(config('nativephp.cleanup_exclude_files'))->toContain('public/images/sunny.icon');
});

it('builds the iOS app icon from the Icon Composer file', function () {
    $this->installer->installIosIcon();

    expect(base_path('nativephp/ios/NativePHP/sunny.icon/icon.json'))->toBeFile()
        ->and(File::files(base_path('nativephp/ios/NativePHP/sunny.icon/Assets')))->toHaveCount(3)
        ->and(appIconNames())->toBe(['sunny', 'sunny', 'sunny', 'sunny']);
});

it('replaces an icon from an earlier build', function () {
    File::ensureDirectoryExists(base_path('nativephp/ios/NativePHP/old.icon'));
    File::ensureDirectoryExists(base_path('nativephp/ios/NativePHP/sunny.icon'));
    File::put(base_path('nativephp/ios/NativePHP/sunny.icon/stale.png'), '');

    $this->installer->installIosIcon();

    expect(base_path('nativephp/ios/NativePHP/old.icon'))->not->toBeDirectory()
        ->and(base_path('nativephp/ios/NativePHP/sunny.icon/stale.png'))->not->toBeFile()
        ->and(base_path('nativephp/ios/NativePHP/sunny.icon/icon.json'))->toBeFile();
});

it('falls back to the PNG app icon set without an Icon Composer file', function () {
    $this->installer->installIosIcon();

    config(['nativephp.ios_icon' => null]);

    $this->installer->installIosIcon();

    expect(base_path('nativephp/ios/NativePHP/sunny.icon'))->not->toBeDirectory()
        ->and(appIconNames())->toBe(['AppIcon', 'AppIcon', 'AppIcon', 'AppIcon']);
});

it('quotes icon names Xcode needs quoted', function () {
    File::moveDirectory(base_path('public/images/sunny.icon'), base_path('public/images/Sunny Home.icon'));
    config(['nativephp.ios_icon' => 'public/images/Sunny Home.icon']);

    $this->installer->installIosIcon();

    expect(base_path('nativephp/ios/NativePHP/Sunny Home.icon/icon.json'))->toBeFile()
        ->and(appIconNames())->toBe(['"Sunny Home"', '"Sunny Home"', '"Sunny Home"', '"Sunny Home"']);
});
