<?php

use App\Models\LegoBin;
use App\Models\LegoPiece;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lego_bin_piece', function (Blueprint $table) {
            $table->foreignIdFor(LegoBin::class, 'bin_id');
            $table->foreignIdFor(LegoPiece::class, 'piece_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lego_bin_piece');
    }
};
