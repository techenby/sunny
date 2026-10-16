<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Head\Enums\ImageType;
use Laravel\Head\Enums\OgType;
use Laravel\Head\Enums\TwitterCard;
use Laravel\Head\Facades\Head;
use Laravel\Head\HeadBuilder;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureHead();
        $this->configurePassport();
    }

    protected function configureHead(): void
    {
        Head::defaults(fn (HeadBuilder $head) => $head
            ->title(config('app.name'), suffix: ' - ' . config('app.name'))
            ->description(__('Recipes, lists, routines, calendars, and everything in storage, shared with your whole family. On the web, on your phone, and on the wall.'))
            ->canonical()
            ->og(type: OgType::Website, siteName: config('app.name'))
            ->ogImage(asset('images/og-image.png'), alt: __('Sunny Home: your household, organized'), width: 1200, height: 630, type: ImageType::Png)
            ->twitter(card: TwitterCard::SummaryWithLargeImage)
            ->icon('/favicons/favicon-96x96.png', type: ImageType::Png, sizes: '96x96')
            ->favicon('/favicons/favicon.svg', type: ImageType::Svg)
            ->link('shortcut icon', '/favicons/favicon.ico')
            ->appleTouchIcon('/favicons/apple-touch-icon.png', sizes: '180x180')
            ->appleWebAppTitle('Sunny')
            ->manifest('/favicons/site.webmanifest'));
    }

    protected function configurePassport(): void
    {
        Passport::authorizationView(function (array $parameters) {
            Head::title('Authorize app');

            return view('pages::auth.oauth-authorize', $parameters);
        });

        Passport::tokensExpireIn(CarbonInterval::day());
        Passport::refreshTokensExpireIn(CarbonInterval::days(90));
    }

    protected function configureGates(): void
    {
        Gate::define('admin', function (User $user): bool {
            return $user->email === 'andy@techenby.com';
        });
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
