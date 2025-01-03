<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lego_groups', fn ($table) => $table->renameColumn('has_pieces', 'has_parts'));

        Schema::rename('lego_pieces', 'lego_parts');

        Schema::rename('lego_bin_piece', 'lego_bin_part');
        Schema::table('lego_bin_part', fn ($table) => $table->renameColumn('piece_id', 'part_id'));
    }

    public function down(): void
    {
        Schema::table('lego_groups', fn ($table) => $table->renameColumn('has_parts', 'has_pieces'));

        Schema::rename('lego_parts', 'lego_pieces');

        Schema::rename('lego_bin_part', 'lego_bin_piece');
        Schema::table('lego_bin_piece', fn ($table) => $table->renameColumn('part_id', 'piece_id'));
    }
};
