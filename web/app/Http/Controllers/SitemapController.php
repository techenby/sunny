<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = collect(['home', 'costs', 'privacy', 'terms'])
            ->map(fn (string $name): string => '    <url><loc>' . e(route($name)) . '</loc></url>')
            ->implode("\n");

        $xml = <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
            {$urls}
            </urlset>
            XML;

        return response($xml, headers: ['Content-Type' => 'application/xml']);
    }
}
