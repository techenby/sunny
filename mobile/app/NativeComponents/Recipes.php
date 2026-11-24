<?php

namespace App\NativeComponents;

use App\Concerns\ChecksSunnySync;
use App\Models\Recipe;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;

class Recipes extends Component
{
    use ChecksSunnySync;

    public string $search = '';

    /**
     * The last downloaded recipes, read entirely from the local database.
     *
     * @return list<array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: list<array<string, mixed>>|null, instructions: list<array<string, mixed>>|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}>
     */
    public static function all(): array
    {
        return Recipe::forActiveTeam()->orderBy('id')->get()->toArray();
    }

    /**
     * @return array{id: int, parent_id: int|null, name: string, source: string|null, servings: string|null, prep_time: string|null, cook_time: string|null, total_time: string|null, description: string|null, ingredients: list<array<string, mixed>>|null, instructions: list<array<string, mixed>>|null, notes: string|null, nutrition: string|null, tags: list<string>|null, created_at: string, updated_at: string}|null
     */
    public static function find(int $id): ?array
    {
        return Recipe::forActiveTeam()->where(fn ($query) => $query->whereKey($id)->orWhere('local_id', $id))->first()?->toArray();
    }

    /**
     * The most recently added or updated recipes, newest first.
     *
     * @return list<array<string, mixed>>
     */
    public static function recent(int $limit = 3): array
    {
        return Recipe::forActiveTeam()->orderByDesc('updated_at')->limit($limit)->get()->toArray();
    }

    public static function isSourceUrl(?string $source): bool
    {
        return filter_var($source, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($source, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * Mirrors Recipe::shortenedSource() in the web app: a URL's host without "www.", or plain text limited to 30 characters.
     */
    public static function shortenedSource(?string $source): ?string
    {
        if (blank($source)) {
            return null;
        }

        if (static::isSourceUrl($source)) {
            return str_replace('www.', '', parse_url($source, PHP_URL_HOST) ?? $source);
        }

        return Str::limit($source, 30);
    }

    /**
     * @return list<array{id: int, name: string, summary: string|null, photo: string|null}>
     */
    #[Computed]
    public function recipes(): array
    {
        return collect(static::all())
            ->filter(fn (array $recipe): bool => $this->search === '' || Str::contains($recipe['name'], $this->search, ignoreCase: true))
            ->sortBy('name')
            ->map(fn (array $recipe): array => [
                'id' => $recipe['id'],
                'name' => $recipe['name'],
                'summary' => collect([static::shortenedSource($recipe['source']), $recipe['total_time']])->filter()->implode(' · ') ?: null,
                'photo' => $recipe['thumb_path'] ?? $recipe['photo_url'],
            ])
            ->values()
            ->all();
    }

    public function updateSearch(string $query): void
    {
        $this->search = trim($query);
    }

    #[On('sunny-sync-complete')]
    public function onSyncComplete(string $status): void
    {
        if ($status === 'finished') {
            $this->refreshLocalSyncedData();
        }
    }

    protected function refreshLocalSyncedData(): void
    {
        unset($this->recipes);
    }

    public function render(): View
    {
        return view('native.recipes');
    }
}
