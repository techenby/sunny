<?php

namespace App\Jobs;

use App\Models\LegoGroup;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use InvalidArgumentException;
use LogicException;
use Symfony\Component\DomCrawler\Crawler;

class ImportLegoPieces implements ShouldQueue
{
    use Queueable;

    public const string ENTRY_POINT = 'https://brickarchitect.com/parts/';

    public function __construct()
    {
        //
    }

    public function handle(): void
    {
        $this->getCategories();

        foreach(LegoGroup::parents()->get() as $group) {
            $this->getSubcategories($group);

            $categories = LegoGroup::forParent($group)->get();
            foreach ($categories as $category) {
                $this->getSubcategories($category);
            }

            $subCategories = LegoGroup::whereIn('parent_id', $categories->pluck('id'))->get();
            foreach ($subCategories as $subSubCategory) {
                $this->getSubcategories($subSubCategory);
            }
        }

        $pieces = LegoGroup::whereTrue('has_pieces')->get();
        foreach ($pieces as $group) {
            $this->getPiecesFor($group);
        }
    }

    public function getCategories(): void
    {
        $html = file_get_contents(self::ENTRY_POINT);

        $categories = (new Crawler($html))->filter('.categorylistitem')
            ->each(function ($node, $i) {
                $nameNode = $node->filter('.categorylistitem_name a');
                return [
                    'name' => $name = str($nameNode->text())->after('. '),
                    'slug' => $name->slug(),
                    'href' => $nameNode->attr('href'),
                    'summary' => $node->filter('.categorylistitem_summary')->text(),
                ];
            });

        LegoGroup::upsert($categories, uniqueBy: ['slug'], update: ['name', 'href', 'slug', 'summary']);
    }

    public function getSubcategories($category)
    {
        $html = file_get_contents($category->href);

        $crawler = new Crawler($html);

        try {
            $description = $crawler->filter('.main .category_description')->html();
            $category->update(['description' => $description]);
        } catch (InvalidArgumentException $e) {}

        $categories = $crawler->filter('.part_category h2.partcategoryname')
            ->each(function ($node, $i) use ($category) {
                $nameNode = $node->filter('a');
                return [
                    'parent_id' => $category->id,
                    'name' => $name = $nameNode->text(),
                    'slug' => str($category->slug)->append(' ', $name)->slug(),
                    'href' => $nameNode->attr('href'),
                    'summary' => $node->siblings()->filter('p')->text(),
                ];
            });

        if (empty($categories)) {
            $category->update(['has_pieces' => true]);
            return;
        }

        LegoGroup::upsert($categories, uniqueBy: ['slug'], update: ['name', 'href', 'slug', 'summary']);
    }

    public function getPiecesFor($group)
    {

    }
}
