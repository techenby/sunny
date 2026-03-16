<?php

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', '../resources/views');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toHaveOne', function () {
    return $this->toHaveCount(1);
});

expect()->extend('toBeTrashed', function () {
    return $this->deleted_at->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function amazonFixture(): UploadedFile
{
    return new UploadedFile(
        path: base_path('tests/Fixtures/csv/amazon-import.csv'),
        originalName: 'amazon-import.csv',
        test: true,
    );
}

function amazonFixtureUpload(): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'amazon-import.csv',
        file_get_contents(base_path('tests/Fixtures/csv/amazon-import.csv')),
    );
}

function fakeRecipeHtml(array $schema): string
{
    $json = json_encode($schema);

    return <<<HTML
    <html><head>
    <script type="application/ld+json">{$json}</script>
    </head><body></body></html>
    HTML;
}
