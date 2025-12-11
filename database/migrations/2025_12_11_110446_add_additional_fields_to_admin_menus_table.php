<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('admin_menus', function (Blueprint $table) {
            // Ajouter les nouveaux champs après 'name'
            $table->string('title', 100)->nullable()->after('name');
            $table->enum('type', ['item', 'collapse', 'group'])->default('item')->after('title');
            $table->string('translate', 100)->nullable()->after('type');

            // Ajouter les champs URL après 'route'
            $table->string('link', 255)->nullable()->after('route');
            $table->string('url', 255)->nullable()->after('link');
            $table->string('path', 255)->nullable()->after('url');

            // Ajouter description après 'permission'
            $table->text('description')->nullable()->after('permission');

            // Ajouter les champs CSS et comportement après 'description'
            $table->string('classes', 100)->nullable()->after('description');
            $table->string('group_classes', 100)->nullable()->after('classes');
            $table->boolean('hidden')->default(false)->after('group_classes');
            $table->boolean('exact_match')->default(false)->after('hidden');
            $table->boolean('external')->default(false)->after('exact_match');
            $table->boolean('target')->default(false)->after('external');
            $table->boolean('breadcrumbs')->default(true)->after('target');
            $table->boolean('disabled')->default(false)->after('breadcrumbs');
            $table->boolean('is_main_parent')->default(false)->after('disabled');

            // Ajouter roles après 'is_active'
            $table->json('roles')->nullable()->after('is_active');

            // Ajouter index pour améliorer les performances
            $table->index(['is_active', 'hidden']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admin_menus', function (Blueprint $table) {
            // Supprimer les index
            $table->dropIndex(['is_active', 'hidden']);
            $table->dropIndex(['type']);

            // Supprimer les colonnes
            $table->dropColumn([
                'title',
                'type',
                'translate',
                'link',
                'url',
                'path',
                'description',
                'classes',
                'group_classes',
                'hidden',
                'exact_match',
                'external',
                'target',
                'breadcrumbs',
                'disabled',
                'is_main_parent',
                'roles'
            ]);
        });
    }
};
