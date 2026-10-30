<?php

use App\Http\Controllers\AppLinkAssociationController;
use App\Http\Controllers\CostsController;
use App\Http\Controllers\SitemapController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Head\Facades\Schema;

Route::view('/', 'welcome')->name('home')->withHead(schema: [
    Schema::webSite()
        ->name('Sunny Home')
        ->url(config('app.url'))
        ->toJsonLd(),
    Schema::webApplication()
        ->name('Sunny Home')
        ->url(config('app.url'))
        ->description('Recipes, lists, routines, calendars, and everything in storage, shared with your whole family.')
        ->applicationCategory('LifestyleApplication')
        ->operatingSystem('Web')
        ->isAccessibleForFree(true)
        ->offers(Schema::offer()->price(0)->currency('USD'))
        ->author(Schema::person()->name('Andy Swick'))
        ->sameAs(['https://github.com/techenby/sunny'])
        ->toJsonLd(),
]);
Route::view('privacy', 'legal.privacy')->name('privacy')->withHead(title: 'Privacy Policy');
Route::view('terms', 'legal.terms')->name('terms')->withHead(title: 'Terms of Service');
Route::get('sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('costs', CostsController::class)->name('costs')->withHead(title: 'What it costs');

Route::get('.well-known/apple-app-site-association', [AppLinkAssociationController::class, 'apple'])->name('app-links.apple');
Route::get('.well-known/assetlinks.json', [AppLinkAssociationController::class, 'android'])->name('app-links.android');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard')->withHead(title: 'Dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept')->withHead(title: 'Teams');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/kiosk.php';
require __DIR__ . '/lists.php';
require __DIR__ . '/recipes.php';
require __DIR__ . '/routines.php';
require __DIR__ . '/settings.php';
