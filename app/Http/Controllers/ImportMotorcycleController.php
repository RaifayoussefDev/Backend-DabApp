<?php

namespace App\Http\Controllers;

use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;
use App\Models\MotorcycleDetail;
use App\Models\MotorcycleType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportMotorcycleController extends Controller
{
    public function index()
    {
        return view('motorcycles.import');
    }

    public function import(Request $request)
    {
        // ✅ Augmenter limites avant de commencer
        ini_set('max_execution_time', 1800); // 30 minutes
        ini_set('memory_limit', '1024M');    // augmente mémoire si fichier lourd

        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv'
        ]);
        $request->validate([
            'excel_file' => 'required|mimes:xlsx,xls,csv'
        ]);

        try {
            $file = $request->file('excel_file');
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $data = $worksheet->toArray();

            // Supprimer la ligne d'en-tête
            array_shift($data);

            $imported = 0;
            $errors = [];
            $batchSize = 50; // Commit toutes les 50 lignes pour éviter le timeout DB

            DB::beginTransaction();
            
            foreach ($data as $index => $row) {
                try {
                    // Vérifier si la ligne n'est pas vide (au moins Make et Model requis)
                    if (empty(trim($row[3] ?? '')) || empty(trim($row[4] ?? ''))) {
                        continue;
                    }

                    $this->importMotorcycleRow($row);
                    $imported++;

                    // Commit par lots pour libérer la DB
                    if (($imported % $batchSize) === 0) {
                        DB::commit();
                        DB::beginTransaction();
                    }

                } catch (\Exception $e) {
                    $errors[] = "Ligne " . ($index + 2) . ": " . $e->getMessage();
                }
            }

            DB::commit(); // Commit final

            // Utiliser la session pour passer les erreurs d'import
            if (count($errors) > 0) {
                return redirect()->back()->with([
                    'success' => "Import terminé ! {$imported} motos importées.",
                    'importErrors' => $errors
                ]);
            }

            return redirect()->back()->with('success', "Import réussi ! {$imported} motos importées.");
        } catch (\Exception $e) {
            // En cas de crash majeur hors de la boucle
            if (DB::transactionLevel() > 0) {
                DB::rollback();
            }
            return redirect()->back()->with('error', 'Erreur lors de l\'import: ' . $e->getMessage());
        }
    }

    private function importMotorcycleRow($row)
    {
        // Mapping des colonnes (basé sur votre exemple)
        $data = [
            'page_url' => trim($row[0] ?? ''),
            'image_url' => trim($row[1] ?? ''),
            'full_name' => trim($row[2] ?? ''),
            'make' => trim($row[3] ?? ''),
            'model' => trim($row[4] ?? ''),
            'year' => trim($row[5] ?? ''),
            'category' => trim($row[6] ?? ''),
            'rating' => trim($row[7] ?? ''),
            'price' => trim($row[8] ?? ''),
            'displacement' => trim($row[9] ?? ''),
            'engine_type' => trim($row[10] ?? ''),
            'engine_details' => trim($row[11] ?? ''),
            'power' => trim($row[12] ?? ''),
            'torque' => trim($row[13] ?? ''),
            'top_speed' => trim($row[14] ?? ''),
            'quarter_mile' => trim($row[15] ?? ''),
            'acceleration_0_100' => trim($row[16] ?? ''),
            'acceleration_60_140' => trim($row[17] ?? ''),
            'max_rpm' => trim($row[18] ?? ''),
            'compression' => trim($row[19] ?? ''),
            'bore_stroke' => trim($row[20] ?? ''),
            'valves_per_cylinder' => trim($row[21] ?? ''),
            'fuel_system' => trim($row[22] ?? ''),
            'fuel_control' => trim($row[23] ?? ''),
            'ignition' => trim($row[24] ?? ''),
            'lubrication_system' => trim($row[25] ?? ''),
            'cooling_system' => trim($row[26] ?? ''),
            'gearbox' => trim($row[27] ?? ''),
            'transmission_type' => trim($row[28] ?? ''),
            'clutch' => trim($row[29] ?? ''),
            'driveline' => trim($row[30] ?? ''),
            'fuel_consumption' => trim($row[31] ?? ''),
            'greenhouse_gases' => trim($row[32] ?? ''),
            'emission_details' => trim($row[33] ?? ''),
            'exhaust_system' => trim($row[34] ?? ''),
            'frame_type' => trim($row[35] ?? ''),
            'rake' => trim($row[36] ?? ''),
            'trail' => trim($row[37] ?? ''),
            'front_suspension' => trim($row[38] ?? ''),
            'front_wheel_travel' => trim($row[39] ?? ''),
            'rear_suspension' => trim($row[40] ?? ''),
            'rear_wheel_travel' => trim($row[41] ?? ''),
            'front_tire' => trim($row[42] ?? ''),
            'rear_tire' => trim($row[43] ?? ''),
            'front_brakes' => trim($row[44] ?? ''),
            'front_brakes_diameter' => trim($row[45] ?? ''),
            'rear_brakes' => trim($row[46] ?? ''),
            'rear_brakes_diameter' => trim($row[47] ?? ''),
            'wheels' => trim($row[48] ?? ''),
            'seat' => trim($row[49] ?? ''),
            'dry_weight' => trim($row[50] ?? ''),
            'wet_weight' => trim($row[51] ?? ''),
            'power_weight_ratio' => trim($row[52] ?? ''),
            'front_weight_percentage' => trim($row[53] ?? ''),
            'rear_weight_percentage' => trim($row[54] ?? ''),
            'seat_height' => trim($row[55] ?? ''),
            'alternate_seat_height' => trim($row[56] ?? ''),
            'overall_height' => trim($row[57] ?? ''),
            'overall_length' => trim($row[58] ?? ''),
            'overall_width' => trim($row[59] ?? ''),
            'ground_clearance' => trim($row[60] ?? ''),
            'wheelbase' => trim($row[61] ?? ''),
            'fuel_capacity' => trim($row[62] ?? ''),
            'color_options' => trim($row[63] ?? ''),
            'starter' => trim($row[64] ?? ''),
            'instruments' => trim($row[65] ?? ''),
            'electrical' => trim($row[66] ?? ''),
            'light' => trim($row[67] ?? ''),
            'carrying_capacity' => trim($row[68] ?? ''),
            'factory_warranty' => trim($row[69] ?? ''),
            'comments' => trim($row[70] ?? ''),
        ];

        // Validation des données obligatoires
        if (empty($data['make']) || empty($data['model'])) {
            throw new \Exception('Make et Model sont obligatoires');
        }

        // Créer ou récupérer la marque
        $brand = MotorcycleBrand::firstOrCreate([
            'name' => $data['make']
        ]);

        // Créer ou récupérer le type (basé sur la catégorie)
        $type = null;
        if (!empty($data['category'])) {
            $type = MotorcycleType::firstOrCreate([
                'name' => $data['category']
            ]);
        }

        // Créer ou récupérer le modèle
        $model = MotorcycleModel::firstOrCreate([
            'brand_id' => $brand->id,
            'name' => $data['model']
        ], [
            'type_id' => $type ? $type->id : null
        ]);

        // Créer ou récupérer l'année
        $year = null;
        if (!empty($data['year']) && is_numeric($data['year'])) {
            $year = MotorcycleYear::firstOrCreate([
                'model_id' => $model->id,
                'year' => (int)$data['year']
            ]);
        }

        // Créer les détails de la moto
        if ($year) {
            MotorcycleDetail::updateOrCreate([
                'year_id' => $year->id
            ], [
                'displacement' => $this->cleanNumericValue($data['displacement']),
                'engine_type' => $data['engine_type'] ?: null,
                'engine_details' => $data['engine_details'] ?: null,
                'power' => $this->cleanNumericValue($data['power']),
                'torque' => $this->cleanNumericValue($data['torque']),
                'top_speed' => $this->cleanNumericValue($data['top_speed']),
                'quarter_mile' => $this->cleanNumericValue($data['quarter_mile']),
                'acceleration_0_100' => $this->cleanNumericValue($data['acceleration_0_100']),
                'max_rpm' => $this->cleanNumericValue($data['max_rpm']),
                'compression' => $data['compression'] ?: null,
                'bore_stroke' => $data['bore_stroke'] ?: null,
                'valves_per_cylinder' => $this->cleanNumericValue($data['valves_per_cylinder']),
                'fuel_system' => $data['fuel_system'] ?: null,
                'gearbox' => $data['gearbox'] ?: null,
                'transmission_type' => $data['transmission_type'] ?: null,
                'front_suspension' => $data['front_suspension'] ?: null,
                'rear_suspension' => $data['rear_suspension'] ?: null,
                'front_tire' => $data['front_tire'] ?: null,
                'rear_tire' => $data['rear_tire'] ?: null,
                'front_brakes' => $data['front_brakes'] ?: null,
                'rear_brakes' => $data['rear_brakes'] ?: null,
                'dry_weight' => $this->cleanNumericValue($data['dry_weight']),
                'wet_weight' => $this->cleanNumericValue($data['wet_weight']),
                'seat_height' => $this->cleanNumericValue($data['seat_height']),
                'overall_length' => $this->cleanNumericValue($data['overall_length']),
                'overall_width' => $this->cleanNumericValue($data['overall_width']),
                'overall_height' => $this->cleanNumericValue($data['overall_height']),
                'ground_clearance' => $this->cleanNumericValue($data['ground_clearance']),
                'wheelbase' => $this->cleanNumericValue($data['wheelbase']),
                'fuel_capacity' => $this->cleanNumericValue($data['fuel_capacity']),
                'rating' => $this->cleanNumericValue($data['rating']),
                'price' => $this->cleanNumericValue($data['price']),
                
                // New Fields Mapping
                'fuel_control' => $data['fuel_control'] ?: null,
                'ignition' => $data['ignition'] ?: null,
                'lubrication_system' => $data['lubrication_system'] ?: null,
                'cooling_system' => $data['cooling_system'] ?: null,
                'clutch' => $data['clutch'] ?: null,
                'driveline' => $data['driveline'] ?: null,
                'fuel_consumption' => $data['fuel_consumption'] ?: null, // Keeping as string to preserve units if needed
                'greenhouse_gases' => $data['greenhouse_gases'] ?: null,
                'emission_details' => $data['emission_details'] ?: null,
                'exhaust_system' => $data['exhaust_system'] ?: null,
                'frame_type' => $data['frame_type'] ?: null,
                'rake' => $data['rake'] ?: null,
                'trail' => $data['trail'] ?: null,
                'front_wheel_travel' => $data['front_wheel_travel'] ?: null,
                'rear_wheel_travel' => $data['rear_wheel_travel'] ?: null,
                'front_brakes_diameter' => $data['front_brakes_diameter'] ?: null,
                'rear_brakes_diameter' => $data['rear_brakes_diameter'] ?: null,
                'wheels' => $data['wheels'] ?: null,
                'seat' => $data['seat'] ?: null,
                'power_weight_ratio' => $data['power_weight_ratio'] ?: null,
                'front_weight_percentage' => $data['front_weight_percentage'] ?: null,
                'rear_weight_percentage' => $data['rear_weight_percentage'] ?: null,
                'alternate_seat_height' => $data['alternate_seat_height'] ?: null,
                'carrying_capacity' => $data['carrying_capacity'] ?: null,
                'color_options' => $data['color_options'] ?: null,
                'starter' => $data['starter'] ?: null,
                'instruments' => $data['instruments'] ?: null,
                'electrical' => $data['electrical'] ?: null,
                'light' => $data['light'] ?: null,
                'factory_warranty' => $data['factory_warranty'] ?: null,
                'comments' => $data['comments'] ?: null,
                'acceleration_60_140' => $data['acceleration_60_140'] ?: null,
            ]);
        }
    }

    private function cleanNumericValue($value)
    {
        if (empty($value) || !is_string($value)) {
            return null;
        }

        // Supprimer tous les caractères non numériques sauf le point et la virgule
        $cleaned = preg_replace('/[^\d.,]/', '', $value);

        // Remplacer la virgule par un point
        $cleaned = str_replace(',', '.', $cleaned);

        // Convertir en float si possible
        return is_numeric($cleaned) ? (float)$cleaned : null;
    }

    public function downloadTemplate()
    {
        $headers = [
            'Page URL',
            'Image URL',
            'Full name',
            'Make',
            'Model',
            'Year',
            'Category',
            'Rating',
            'Price as new (MSRP)',
            'Displacement',
            'Engine type',
            'Engine details',
            'Power',
            'Torque',
            'Top speed',
            '1/4 mile (0.4 km)',
            '0-100 km/h (0-62 mph)',
            '60-140 km/h (37-87 mph), highest gear',
            'Max RPM',
            'Compression',
            'Bore x stroke',
            'Valves per cylinder',
            'Fuel system',
            'Fuel control',
            'Ignition',
            'Lubrication system',
            'Cooling system',
            'Gearbox',
            'Transmission type',
            'Clutch',
            'Driveline',
            'Fuel consumption',
            'Greenhouse gases',
            'Emission details',
            'Exhaust system',
            'Frame type',
            'Rake (fork angle)',
            'Trail',
            'Front suspension',
            'Front wheel travel (mm)',
            'Rear suspension',
            'Rear wheel travel (mm)',
            'Front tyre',
            'Rear tyre',
            'Front brakes',
            'Front brakes diameter',
            'Rear brakes',
            'Rear brakes diameter',
            'Wheels',
            'Seat',
            'Dry weight',
            'Weight incl. oil, gas, etc',
            'Power/weight ratio',
            'Front percentage of weight',
            'Rear percentage of weight',
            'Seat height',
            'Alternate seat height',
            'Overall height',
            'Overall length',
            'Overall width',
            'Ground clearance',
            'Wheelbase',
            'Fuel capacity',
            'Color options',
            'Starter',
            'Instruments',
            'Electrical',
            'Light',
            'Carrying capacity',
            'Factory warranty',
            'Comments'
        ];

        $filename = 'motorcycle_import_template.csv';

        $response = response()->stream(function () use ($headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);
            fclose($handle);
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);

        return $response;
    }
}
