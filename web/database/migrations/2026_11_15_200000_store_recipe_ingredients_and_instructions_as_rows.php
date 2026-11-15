<?php

use App\Support\Recipes\RecipeLines;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->json('ingredient_rows')->nullable();
            $table->json('instruction_rows')->nullable();
        });

        DB::table('recipes')->orderBy('id')->chunkById(100, function (Collection $recipes) {
            foreach ($recipes as $recipe) {
                [$instructions, $notes] = $this->splitNotes(RecipeLines::instructions($recipe->instructions) ?? []);

                DB::table('recipes')->where('id', $recipe->id)->update([
                    'ingredient_rows' => $this->encode(RecipeLines::ingredients($recipe->ingredients)),
                    'instruction_rows' => $this->encode($instructions === [] ? null : $instructions),
                    'notes' => $notes === [] ? $recipe->notes : trim($recipe->notes . "\n\n" . implode("\n", $notes)),
                ]);
            }
        });

        $this->replace('ingredient_rows', 'instruction_rows');
    }

    public function down(): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->text('ingredient_html')->nullable();
            $table->text('instruction_html')->nullable();
        });

        DB::table('recipes')->orderBy('id')->chunkById(100, function (Collection $recipes) {
            foreach ($recipes as $recipe) {
                DB::table('recipes')->where('id', $recipe->id)->update([
                    'ingredient_html' => $this->html($recipe->ingredients, 'ul'),
                    'instruction_html' => $this->html($recipe->instructions, 'ol'),
                ]);
            }
        });

        $this->replace('ingredient_html', 'instruction_html');
    }

    private function replace(string $ingredients, string $instructions): void
    {
        Schema::table('recipes', function (Blueprint $table) {
            $table->dropColumn(['ingredients', 'instructions']);
        });

        Schema::table('recipes', function (Blueprint $table) use ($ingredients) {
            $table->renameColumn($ingredients, 'ingredients');
        });

        Schema::table('recipes', function (Blueprint $table) use ($instructions) {
            $table->renameColumn($instructions, 'instructions');
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{list<array<string, mixed>>, list<string>}
     */
    private function splitNotes(array $rows): array
    {
        $instructions = [];
        $notes = [];
        $inNotes = false;

        foreach ($rows as $row) {
            if (preg_match('/^notes?:?$/iu', $row['text']) === 1) {
                $inNotes = true;
            } elseif ($row['type'] === 'heading') {
                $inNotes = false;
                $instructions[] = $row;
            } elseif ($inNotes) {
                $notes[] = $row['text'];
            } elseif (preg_match('/^notes?\s*[:–-]\s*(.+)$/isu', $row['text'], $matches) === 1) {
                $notes[] = $matches[1];
            } else {
                $instructions[] = $row;
            }
        }

        return [$instructions, $notes];
    }

    /** @param  list<array<string, mixed>>|null  $rows */
    private function encode(?array $rows): ?string
    {
        return $rows === null
            ? null
            : json_encode($rows, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function html(?string $rows, string $tag): ?string
    {
        $decoded = json_decode($rows ?? '[]', true);

        if (! is_array($decoded) || $decoded === []) {
            return null;
        }

        $items = array_map(fn (array $row): string => $row['type'] === 'heading'
            ? '<li><strong>' . e($row['text']) . '</strong></li>'
            : '<li>' . e($row['text']) . '</li>', $decoded);

        return "<{$tag}>" . implode('', $items) . "</{$tag}>";
    }
};
