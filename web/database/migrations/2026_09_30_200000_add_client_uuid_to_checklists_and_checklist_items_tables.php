<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['checklists' => 'team_id', 'checklist_items' => 'checklist_id'] as $name => $scope) {
            Schema::table($name, function (Blueprint $table) use ($scope) {
                $table->uuid('client_uuid')->nullable();
                $table->unique([$scope, 'client_uuid']);
            });
        }
    }

    public function down(): void
    {
        foreach (['checklists' => 'team_id', 'checklist_items' => 'checklist_id'] as $name => $scope) {
            Schema::table($name, function (Blueprint $table) use ($scope) {
                $table->dropUnique([$scope, 'client_uuid']);
                $table->dropColumn('client_uuid');
            });
        }
    }
};
