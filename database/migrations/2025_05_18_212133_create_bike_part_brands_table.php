<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bike_part_brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // ✅ Insertion des données par défaut
        DB::table('bike_part_brands')->insert([
            ['name' => 'NGK', 'created_at' => now(), 'updated_at' => now()], // bougies
            ['name' => 'Brembo', 'created_at' => now(), 'updated_at' => now()], // freinage moto
            ['name' => 'Bosch', 'created_at' => now(), 'updated_at' => now()], // pièces électroniques, bougies
            ['name' => 'Denso', 'created_at' => now(), 'updated_at' => now()], // pièces auto/moto (bougies)
            ['name' => 'Magneti Marelli', 'created_at' => now(), 'updated_at' => now()], // électronique, éclairage
            ['name' => 'Hella', 'created_at' => now(), 'updated_at' => now()], // éclairage
            ['name' => 'Valeo', 'created_at' => now(), 'updated_at' => now()], // éclairage, embrayage
            ['name' => 'SKF', 'created_at' => now(), 'updated_at' => now()], // roulements
            ['name' => 'Continental', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Sachs', 'created_at' => now(), 'updated_at' => now()], // suspensions, embrayages
            ['name' => 'TRW', 'created_at' => now(), 'updated_at' => now()], // freinage
            ['name' => 'Febi Bilstein', 'created_at' => now(), 'updated_at' => now()], // pièces suspension, direction
            ['name' => 'Mann Filter', 'created_at' => now(), 'updated_at' => now()], // filtres (aussi moto)
            ['name' => 'K&N', 'created_at' => now(), 'updated_at' => now()], // filtres performance moto
            ['name' => 'Acerbis', 'created_at' => now(), 'updated_at' => now()], // accessoires moto
            ['name' => 'Renthal', 'created_at' => now(), 'updated_at' => now()], // guidons, accessoires cross/enduro
            ['name' => 'Ohlins', 'created_at' => now(), 'updated_at' => now()], // suspensions premium moto
            ['name' => 'Yoshimura', 'created_at' => now(), 'updated_at' => now()], // échappements
            ['name' => 'Akrapovic', 'created_at' => now(), 'updated_at' => now()], // échappements moto
            ['name' => 'Leo Vince', 'created_at' => now(), 'updated_at' => now()], // échappements
            ['name' => 'Arrow', 'created_at' => now(), 'updated_at' => now()], // échappements
            ['name' => 'Dynojet', 'created_at' => now(), 'updated_at' => now()], // réglage carburateur/injection
            ['name' => 'Power Commander', 'created_at' => now(), 'updated_at' => now()], // gestion moteur
            ['name' => 'V-Force', 'created_at' => now(), 'updated_at' => now()], // membranes carburateur
            ['name' => 'Pro Circuit', 'created_at' => now(), 'updated_at' => now()], // échappements motocross
            ['name' => 'FMF Racing', 'created_at' => now(), 'updated_at' => now()], // échappements moto
            ['name' => 'HMF Racing', 'created_at' => now(), 'updated_at' => now()], // échappements moto
            ['name' => 'Vortex', 'created_at' => now(), 'updated_at' => now()], // accessoires moto racing
            ['name' => 'BMC Air Filters', 'created_at' => now(), 'updated_at' => now()], // filtres air moto
            ['name' => 'EBC Brakes', 'created_at' => now(), 'updated_at' => now()], // plaquettes/frein moto
            ['name' => 'Galfer', 'created_at' => now(), 'updated_at' => now()], // frein moto (disques, plaquettes)
            ['name' => 'Ferodo', 'created_at' => now(), 'updated_at' => now()], // frein moto
            ['name' => 'Eibach', 'created_at' => now(), 'updated_at' => now()], // suspensions (aussi moto)
            ['name' => 'Hyperpro', 'created_at' => now(), 'updated_at' => now()], // suspensions moto
            ['name' => 'Wilbers', 'created_at' => now(), 'updated_at' => now()], // suspensions moto
            ['name' => 'Hagon', 'created_at' => now(), 'updated_at' => now()], // suspensions moto
            ['name' => 'Bitubo', 'created_at' => now(), 'updated_at' => now()], // suspensions moto
            ['name' => 'Pirelli', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Michelin', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Dunlop', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Bridgestone', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Metzeler', 'created_at' => now(), 'updated_at' => now()], // pneus moto spécialisés
            ['name' => 'Avon', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Heidenau', 'created_at' => now(), 'updated_at' => now()], // pneus moto trail
            ['name' => 'Shinko', 'created_at' => now(), 'updated_at' => now()], // pneus moto trail/enduro
            ['name' => 'Kenda', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'IRC', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Duro', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Maxxis', 'created_at' => now(), 'updated_at' => now()], // pneus moto
            ['name' => 'Mitas', 'created_at' => now(), 'updated_at' => now()], // pneus moto
        ]);     
    }

    public function down(): void
    {
        Schema::dropIfExists('bike_part_brands');
    }
};
