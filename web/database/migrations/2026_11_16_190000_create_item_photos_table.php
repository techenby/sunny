<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('thumb_path')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->uuid('client_uuid')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'position']);
            $table->unique(['item_id', 'client_uuid']);
        });

        DB::table('item_photos')->insertUsing(
            ['item_id', 'path', 'thumb_path', 'position', 'created_at', 'updated_at'],
            DB::table('items')
                ->whereNotNull('photo_path')
                ->select(['id', 'photo_path', 'thumb_path', DB::raw('0'), 'updated_at', 'updated_at']),
        );

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'thumb_path']);
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('photo_path')->nullable();
            $table->string('thumb_path')->nullable();
        });

        DB::table('item_photos')
            ->whereIn('id', fn (Builder $query) => $query->from('item_photos')->selectRaw('min(id)')->where('position', 0)->groupBy('item_id'))
            ->orderBy('id')
            ->each(fn (object $photo) => DB::table('items')->where('id', $photo->item_id)->update([
                'photo_path' => $photo->path,
                'thumb_path' => $photo->thumb_path,
            ]));

        Schema::dropIfExists('item_photos');
    }
};
