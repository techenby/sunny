<?php

test('lists the public pages in the sitemap', function () {
    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml')
        ->assertSee('<?xml version="1.0" encoding="UTF-8"?>', false)
        ->assertSee('<loc>' . route('home') . '</loc>', false)
        ->assertSee('<loc>' . route('costs') . '</loc>', false)
        ->assertSee('<loc>' . route('privacy') . '</loc>', false)
        ->assertSee('<loc>' . route('terms') . '</loc>', false);
});

test('robots.txt points crawlers at the sitemaps', function () {
    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: https://sunnyhome.app/sitemap.xml')
        ->toContain('Sitemap: https://sunnyhome.app/docs/sitemap.xml');
});
