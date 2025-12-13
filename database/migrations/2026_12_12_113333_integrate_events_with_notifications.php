<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration pour intégrer le système d'événements avec le système de notifications existant
 * 
 * Cette migration ajoute:
 * 1. Support multilingue (arabe/anglais) pour les événements
 * 2. Système d'intérêts (interested users)
 * 3. Types de notifications pour événements dans les templates existants
 */
return new class extends Migration
{
    public function up()
    {
        // ========================================
        // ÉTAPE 1: Ajouter les champs arabes aux events
        // ========================================
        Schema::table('events', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('description_ar')->nullable()->after('description');
            $table->text('short_description_ar')->nullable()->after('short_description');
            $table->string('venue_name_ar')->nullable()->after('venue_name');
            $table->text('address_ar')->nullable()->after('address');
            $table->integer('interests_count')->default(0)->after('participants_count');
        });

        // ========================================
        // ÉTAPE 2: Ajouter les champs arabes aux event_activities
        // ========================================
        Schema::table('event_activities', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('description_ar')->nullable()->after('description');
            $table->string('location_ar')->nullable()->after('location');
        });

        // ========================================
        // ÉTAPE 3: Ajouter les champs arabes aux event_faqs
        // ========================================
        Schema::table('event_faqs', function (Blueprint $table) {
            $table->text('question_ar')->nullable()->after('question');
            $table->text('answer_ar')->nullable()->after('answer');
        });

        // ========================================
        // ÉTAPE 4: Ajouter les champs arabes aux event_categories
        // ========================================
        Schema::table('event_categories', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
            $table->text('description_ar')->nullable()->after('description');
        });

        // ========================================
        // ÉTAPE 5: Ajouter les champs arabes aux event_contacts
        // ========================================
        Schema::table('event_contacts', function (Blueprint $table) {
            $table->string('name_ar')->nullable()->after('name');
        });

        // ========================================
        // ÉTAPE 6: Ajouter les champs arabes aux event_tickets
        // ========================================
        Schema::table('event_tickets', function (Blueprint $table) {
            $table->string('ticket_type_ar')->nullable()->after('ticket_type');
            $table->text('description_ar')->nullable()->after('description');
        });

        // ========================================
        // ÉTAPE 7: Ajouter les champs arabes aux event_updates
        // ========================================
        Schema::table('event_updates', function (Blueprint $table) {
            $table->string('title_ar')->nullable()->after('title');
            $table->text('content_ar')->nullable()->after('content');
        });

        // ========================================
        // ÉTAPE 8: Créer la table event_interests (utilisateurs intéressés)
        // ========================================
        Schema::create('event_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained('events')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent();
            
            $table->unique(['event_id', 'user_id']);
            $table->index(['event_id', 'user_id']);
        });

        // ========================================
        // ÉTAPE 9: Insérer les templates de notifications pour événements
        // dans la table notification_templates EXISTANTE
        // ========================================
        DB::table('notification_templates')->insert([
            [
                'type' => 'event_new',
                'name' => 'New Event Published',
                'description' => 'Sent when a new event is published',
                'title_template' => 'New Event: {{event_title}}',
                'message_template' => 'Check out the new event: {{event_title}} on {{event_date}}',
                'icon' => 'event',
                'color' => '#4CAF50',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'event_date', 'event_location']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'event_update',
                'name' => 'Event Updated',
                'description' => 'Sent when event details are updated',
                'title_template' => 'Event Update: {{event_title}}',
                'message_template' => 'The event {{event_title}} has been updated. Please check the new details.',
                'icon' => 'update',
                'color' => '#2196F3',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'update_details']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'event_reminder',
                'name' => 'Event Reminder',
                'description' => 'Reminder before event starts',
                'title_template' => 'Reminder: {{event_title}} starts in {{hours}} hours',
                'message_template' => 'Don\'t forget! {{event_title}} starts in {{hours}} hours at {{event_location}}',
                'icon' => 'alarm',
                'color' => '#FF9800',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'hours', 'event_location']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'event_cancelled',
                'name' => 'Event Cancelled',
                'description' => 'Sent when event is cancelled',
                'title_template' => 'Event Cancelled: {{event_title}}',
                'message_template' => 'Unfortunately, {{event_title}} has been cancelled. {{reason}}',
                'icon' => 'cancel',
                'color' => '#F44336',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'reason']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'event_registration_confirmed',
                'name' => 'Registration Confirmed',
                'description' => 'Sent when user registers for event',
                'title_template' => 'Registration Confirmed: {{event_title}}',
                'message_template' => 'Your registration for {{event_title}} has been confirmed!',
                'icon' => 'check_circle',
                'color' => '#4CAF50',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'event_date']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type' => 'event_new_participant',
                'name' => 'New Participant (Organizer)',
                'description' => 'Sent to organizer about new participant',
                'title_template' => 'New Registration: {{event_title}}',
                'message_template' => '{{participant_name}} has registered for your event {{event_title}}',
                'icon' => 'person_add',
                'color' => '#2196F3',
                'sound' => 'default',
                'is_active' => true,
                'variables' => json_encode(['event_title', 'participant_name']),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // ========================================
        // ÉTAPE 10: Ajouter les préférences d'événements dans notification_preferences
        // (Ajouter les colonnes pour les notifications d'événements)
        // ========================================
        Schema::table('notification_preferences', function (Blueprint $table) {
            // Vérifier si les colonnes n'existent pas déjà
            if (!Schema::hasColumn('notification_preferences', 'event_new')) {
                $table->boolean('event_new')->default(true)->after('newsletter');
            }
            if (!Schema::hasColumn('notification_preferences', 'event_reminder')) {
                $table->boolean('event_reminder')->default(true)->after('event_new');
            }
            if (!Schema::hasColumn('notification_preferences', 'event_updated')) {
                $table->boolean('event_updated')->default(true)->after('event_reminder');
            }
            if (!Schema::hasColumn('notification_preferences', 'event_cancelled')) {
                $table->boolean('event_cancelled')->default(true)->after('event_updated');
            }
            if (!Schema::hasColumn('notification_preferences', 'event_registration')) {
                $table->boolean('event_registration')->default(true)->after('event_cancelled');
            }
        });
    }

    public function down()
    {
        // Supprimer dans l'ordre inverse
        Schema::dropIfExists('event_interests');

        // Supprimer les colonnes arabes
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn([
                'title_ar', 'description_ar', 'short_description_ar', 
                'venue_name_ar', 'address_ar', 'interests_count'
            ]);
        });

        Schema::table('event_activities', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'description_ar', 'location_ar']);
        });

        Schema::table('event_faqs', function (Blueprint $table) {
            $table->dropColumn(['question_ar', 'answer_ar']);
        });

        Schema::table('event_categories', function (Blueprint $table) {
            $table->dropColumn(['name_ar', 'description_ar']);
        });

        Schema::table('event_contacts', function (Blueprint $table) {
            $table->dropColumn('name_ar');
        });

        Schema::table('event_tickets', function (Blueprint $table) {
            $table->dropColumn(['ticket_type_ar', 'description_ar']);
        });

        Schema::table('event_updates', function (Blueprint $table) {
            $table->dropColumn(['title_ar', 'content_ar']);
        });

        Schema::table('notification_preferences', function (Blueprint $table) {
            $table->dropColumn([
                'event_new', 'event_reminder', 'event_updated', 
                'event_cancelled', 'event_registration'
            ]);
        });

        // Supprimer les templates d'événements
        DB::table('notification_templates')
            ->whereIn('type', [
                'event_new', 'event_update', 'event_reminder', 
                'event_cancelled', 'event_registration_confirmed', 'event_new_participant'
            ])
            ->delete();
    }
};
