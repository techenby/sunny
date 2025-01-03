<?php

namespace Database\Seeders;

use App\Models\LegoColor;
use App\Models\LegoGroup;
use App\Models\LegoPart;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(Filesystem $filesystem): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        if ($filesystem->exists(database_path('fixtures/lego_colors.json'))) {
            $colors = $filesystem->json(database_path('fixtures/lego_colors.json'));

            LegoColor::upsert($colors, uniqueBy: ['id'], update: ['id', 'name', 'hex', 'is_trans', 'external']);
        } else {
            Artisan::call('lego:import-colors');
        }

        if ($filesystem->exists(database_path('fixtures/lego_groups.json'))) {
            $groups = $filesystem->json(database_path('fixtures/lego_groups.json'));
            $parts = $filesystem->json(database_path('fixtures/lego_parts.json'));

            LegoGroup::upsert($groups, uniqueBy: ['id'], update: ['id', 'parent_id', 'name', 'slug', 'has_parts', 'href', 'summary', 'description']);
            LegoPart::upsert($parts, uniqueBy: ['id'], update: ['id', 'group_id', 'name', 'part_number', 'image', 'href']);
        } else {
            Artisan::call('lego:import-parts');
        }
    }
}
