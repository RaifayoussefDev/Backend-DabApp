<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\MotorcycleBrand;
use App\Models\MotorcycleType;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;
use App\Models\MotorcycleDetail;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class MotorcycleImportController extends Controller
{
    /**
     * Import motorcycles from Excel file
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function import(Request $request)
    {
        // Validation du fichier
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $file = $request->file('file');
            $data = $this->parseExcelFile($file);

            $importResult = $this->processImport($data);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Import completed successfully',
                'data' => $importResult
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse the Excel file
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @return array
     */
    private function parseExcelFile($file)
    {
        // Utilisation de Maatwebsite\Excel pour parser le fichier
        $data = [];

        $rows = Excel::toArray([], $file)[0]; // Prendre la première feuille

        // Récupérer les en-têtes depuis la première ligne
        $headers = array_shift($rows);

        foreach ($rows as $row) {
            if (count($headers) === count($row)) {
                $data[] = array_combine($headers, $row);
            }
        }

        return $data;
    }

    /**
     * Process the import of motorcycles data
     *
     * @param array $data
     * @return array
     */
    private function processImport($data)
    {
        $stats = [
            'brands' => 0,
            'types' => 0,
            'models' => 0,
            'years' => 0,
            'details' => 0,
        ];

        foreach ($data as $row) {
            // Skip if essential data is missing
            if (empty($row['Make']) || empty($row['Model']) || empty($row['Year'])) {
                continue;
            }

            // Process brand
            $brand = $this->processMotorcycleBrand($row['Make']);
            $stats['brands'] += $brand['created'] ? 1 : 0;

            // Process type
            $categoryName = !empty($row['Category']) ? $row['Category'] : 'Unspecified category';
            $type = $this->processMotorcycleType($categoryName);
            $stats['types'] += $type['created'] ? 1 : 0;

            // Process model
            $model = $this->processMotorcycleModel($brand['brand']->id, $row['Model'], $type['type']->id);
            $stats['models'] += $model['created'] ? 1 : 0;

            // Process year
            $year = $this->processMotorcycleYear($model['model']->id, (int) $row['Year']);
            $stats['years'] += $year['created'] ? 1 : 0;

            // Process details
            $detail = $this->processMotorcycleDetails($year['year']->id, $row);
            $stats['details'] += $detail ? 1 : 0;
        }

        return $stats;
    }

    /**
     * Process motorcycle brand
     *
     * @param string $brandName
     * @return array
     */
    private function processMotorcycleBrand($brandName)
    {
        $brand = MotorcycleBrand::firstOrCreate(['name' => $brandName]);

        return [
            'brand' => $brand,
            'created' => $brand->wasRecentlyCreated
        ];
    }

    /**
     * Process motorcycle type
     *
     * @param string $typeName
     * @return array
     */
    private function processMotorcycleType($typeName)
    {
        $type = MotorcycleType::firstOrCreate(['name' => $typeName]);

        return [
            'type' => $type,
            'created' => $type->wasRecentlyCreated
        ];
    }

    /**
     * Process motorcycle model
     *
     * @param int $brandId
     * @param string $modelName
     * @param int $typeId
     * @return array
     */
    private function processMotorcycleModel($brandId, $modelName, $typeId)
    {
        $model = MotorcycleModel::firstOrCreate(
            ['brand_id' => $brandId, 'name' => $modelName],
            ['type_id' => $typeId]
        );

        return [
            'model' => $model,
            'created' => $model->wasRecentlyCreated
        ];
    }

    /**
     * Process motorcycle year
     *
     * @param int $modelId
     * @param int $year
     * @return array
     */
    private function processMotorcycleYear($modelId, $year)
    {
        $motorcycleYear = MotorcycleYear::firstOrCreate([
            'model_id' => $modelId,
            'year' => $year
        ]);

        return [
            'year' => $motorcycleYear,
            'created' => $motorcycleYear->wasRecentlyCreated
        ];
    }

    /**
     * Process motorcycle details
     *
     * @param int $yearId
     * @param array $data
     * @return bool
     */
    private function processMotorcycleDetails($yearId, $data)
    {
        // Mapper les colonnes Excel vers les champs de la table details
        $detailsData = [
            'year_id' => $yearId,
            'displacement' => $this->extractNumericValue($data['Displacement'] ?? null),
            'engine_type' => $data['Engine type'] ?? null,
            'engine_details' => $data['Engine details'] ?? null,
            'power' => $this->extractPower($data['Power'] ?? null),
            'torque' => $this->extractTorque($data['Torque'] ?? null),
            'top_speed' => $this->extractNumericValue($data['Top speed'] ?? null),
            'quarter_mile' => $data['1/4 mile (0.4 km)'] ?? null,
            'acceleration_0_100' => $data['0-100 km/h (0-62 mph)'] ?? null,
            'max_rpm' => $this->extractNumericValue($data['Max RPM'] ?? null),
            'compression' => $this->extractNumericValue($data['Compression'] ?? null),
            'bore_stroke' => $data['Bore x stroke'] ?? null,
            'valves_per_cylinder' => $this->extractNumericValue($data['Valves per cylinder'] ?? null),
            'fuel_system' => $data['Fuel system'] ?? null,
            'gearbox' => $data['Gearbox'] ?? null,
            'transmission_type' => $data['Transmission type'] ?? null,
            'front_suspension' => $data['Front suspension'] ?? null,
            'rear_suspension' => $data['Rear suspension'] ?? null,
            'front_tire' => $data['Front tyre'] ?? null,
            'rear_tire' => $data['Rear tyre'] ?? null,
            'front_brakes' => $data['Front brakes'] ?? null,
            'rear_brakes' => $data['Rear brakes'] ?? null,
            'dry_weight' => $this->extractWeight($data['Dry weight'] ?? null),
            'wet_weight' => $this->extractWeight($data['Weight incl. oil, gas, etc'] ?? null),
            'seat_height' => $this->extractNumericValue($data['Seat height'] ?? null),
            'overall_length' => $this->extractNumericValue($data['Overall length'] ?? null),
            'overall_width' => $this->extractNumericValue($data['Overall width'] ?? null),
            'overall_height' => $this->extractNumericValue($data['Overall height'] ?? null),
            'ground_clearance' => $this->extractNumericValue($data['Ground clearance'] ?? null),
            'wheelbase' => $this->extractNumericValue($data['Wheelbase'] ?? null),
            'fuel_capacity' => $this->extractNumericValue($data['Fuel capacity'] ?? null),
            'rating' => $this->extractNumericValue($data['Rating'] ?? null),
            'price' => $this->extractNumericValue($data['Price as new (MSRP)'] ?? null),
        ];

        // Filtrer les valeurs null
        $detailsData = array_filter($detailsData, function ($value) {
            return $value !== null;
        });

        // Vérifier s'il y a au moins quelques détails à enregistrer
        if (count($detailsData) > 2) { // Au moins quelques détails en plus de year_id
            MotorcycleDetail::updateOrCreate(
                ['year_id' => $yearId],
                $detailsData
            );
            return true;
        }

        return false;
    }

    /**
     * Extract numeric value from string
     *
     * @param string|null $value
     * @return float|null
     */
    private function extractNumericValue($value)
    {
        if (empty($value)) {
            return null;
        }

        // Extraire le premier nombre trouvé dans la chaîne
        preg_match('/([\d\.]+)/', $value, $matches);

        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    /**
     * Extract power value in HP
     *
     * @param string|null $value
     * @return float|null
     */
    private function extractPower($value)
    {
        if (empty($value)) {
            return null;
        }

        // Extraire la valeur en HP
        preg_match('/(\d+\.?\d*)\s*HP/', $value, $matches);

        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    /**
     * Extract torque value in Nm
     *
     * @param string|null $value
     * @return float|null
     */
    private function extractTorque($value)
    {
        if (empty($value)) {
            return null;
        }

        // Extraire la valeur en Nm
        preg_match('/(\d+\.?\d*)\s*Nm/', $value, $matches);

        return isset($matches[1]) ? (float) $matches[1] : null;
    }

    /**
     * Extract weight value in kg
     *
     * @param string|null $value
     * @return float|null
     */
    private function extractWeight($value)
    {
        if (empty($value)) {
            return null;
        }

        // Extraire la valeur en kg
        preg_match('/(\d+\.?\d*)\s*kg/', $value, $matches);

        return isset($matches[1]) ? (float) $matches[1] : null;
    }
}