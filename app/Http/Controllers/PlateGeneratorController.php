<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;

class PlateGeneratorController extends Controller
{
    /**
     * Generate plate from API request
     */
    public function generatePlate(Request $request)
    {
        $validated = $request->validate([
            'country' => 'required|in:ksa,uae,dubai',
            'format' => 'nullable|in:png,jpg',
            'top_left' => 'required_if:country,ksa|string',
            'top_right' => 'required_if:country,ksa|string',
            'bottom_left' => 'required_if:country,ksa|string',
            'bottom_right' => 'required_if:country,ksa|string',
            'category_number' => 'required_if:country,uae,dubai|string',
            'plate_number' => 'required_if:country,uae,dubai|string',
            'city_name_ar' => 'nullable|string',
            'city_name_en' => 'nullable|string',
        ]);

        $result = $this->generatePlateInternal($request);

        if ($result) {
            return response()->json([
                'success' => true,
                'message' => 'Plaque générée avec succès',
                'data' => $result
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Erreur lors de la génération'
        ], 500);
    }

    /**
     * Internal method to generate plate (can be called from model or controller)
     */
    public function generatePlateInternal(Request $request, $city = null)
    {
        try {
            $country = $request->input('country');
            $format = $request->input('format', 'png');

            // ✅ LOG COMPLET DES DONNÉES REÇUES
            \Log::info("🎨 PlateGenerator COMPLETE DEBUG", [
                'country' => $country,
                'all_request_data' => $request->all(),
                'top_left' => $request->input('top_left'),
                'top_right' => $request->input('top_right'),
                'bottom_left' => $request->input('bottom_left'),
                'bottom_right' => $request->input('bottom_right'),
                'category_number' => $request->input('category_number'),
                'plate_number' => $request->input('plate_number'),
            ]);

            // Charger les logos
            $logoBase64 = null;
            $motoLogoBase64 = null;

            if ($country === 'ksa') {
                $logoPath = storage_path('app/public/logo.png');
                if (file_exists($logoPath)) {
                    $logoData = base64_encode(file_get_contents($logoPath));
                    $mimeType = mime_content_type($logoPath);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . $logoData;
                } else {
                    $logoBase64 = $this->getDefaultLogo('ksa');
                }
            } elseif ($country === 'uae') {
                $logoPath = storage_path('app/public/abudhabi.png');
                if (file_exists($logoPath)) {
                    $logoData = base64_encode(file_get_contents($logoPath));
                    $mimeType = mime_content_type($logoPath);
                    $logoBase64 = 'data:' . $mimeType . ';base64,' . $logoData;
                } else {
                    $logoBase64 = $this->getDefaultLogo('uae');
                }
            } else { // dubai
                $logoPath = storage_path('app/public/dubai-moto.png');
                if (file_exists($logoPath)) {
                    $logoData = base64_encode(file_get_contents($logoPath));
                    $mimeType = mime_content_type($logoPath);
                    $motoLogoBase64 = 'data:' . $mimeType . ';base64,' . $logoData;
                } else {
                    $motoLogoBase64 = $this->getDefaultLogo('dubai');
                }
            }

            // Générer le HTML selon le pays
            if ($country === 'ksa') {
                // ✅ ENVOYER LES DEUX FORMATS (camelCase ET snake_case)
                $viewData = [
                    // camelCase
                    'topLeft' => $request->input('top_left'),
                    'topRight' => $request->input('top_right'),
                    'bottomLeft' => $request->input('bottom_left'),
                    'bottomRight' => $request->input('bottom_right'),

                    // snake_case
                    'top_left' => $request->input('top_left'),
                    'top_right' => $request->input('top_right'),
                    'bottom_left' => $request->input('bottom_left'),
                    'bottom_right' => $request->input('bottom_right'),

                    'logoBase64' => $logoBase64,
                    'logo_base64' => $logoBase64,
                ];

                \Log::info("🎨 KSA Plate data being sent to view", [
                    'topLeft' => $viewData['topLeft'],
                    'topRight' => $viewData['topRight'],
                    'bottomLeft' => $viewData['bottomLeft'],
                    'bottomRight' => $viewData['bottomRight'],
                ]);

                $html = view('templates.plate', $viewData)->render();
                $windowSize = [700, 500];
            } elseif ($country === 'uae') {
                $viewData = [
                    'categoryNumber' => $request->input('category_number'),
                    'plateNumber' => $request->input('plate_number'),
                    'category_number' => $request->input('category_number'),
                    'plate_number' => $request->input('plate_number'),
                    'logoBase64' => $logoBase64,
                    'logo_base64' => $logoBase64,
                    'cityNameAr' => $request->input('city_name_ar', $city->name_ar ?? 'أبو ظبي'),
                    'cityNameEn' => $request->input('city_name_en', $city->name ?? 'ABU DHABI'),
                    'city_name_ar' => $request->input('city_name_ar', $city->name_ar ?? 'أبو ظبي'),
                    'city_name_en' => $request->input('city_name_en', $city->name ?? 'ABU DHABI'),
                ];

                \Log::info("🎨 UAE Plate data being sent to view", $viewData);

                $html = view('templates.plate_uae', $viewData)->render();
                $windowSize = [700, 500];
            } else { // dubai
                $viewData = [
                    'categoryNumber' => $request->input('category_number'),
                    'plateNumber' => $request->input('plate_number'),
                    'category_number' => $request->input('category_number'),
                    'plate_number' => $request->input('plate_number'),
                    'motoLogoBase64' => $motoLogoBase64,
                    'moto_logo_base64' => $motoLogoBase64,
                    'cityNameAr' => $request->input('city_name_ar', $city->name_ar ?? 'دبي'),
                    'cityNameEn' => $request->input('city_name_en', $city->name ?? 'DUBAI'),
                    'city_name_ar' => $request->input('city_name_ar', $city->name_ar ?? 'دبي'),
                    'city_name_en' => $request->input('city_name_en', $city->name ?? 'DUBAI'),
                ];

                \Log::info("🎨 Dubai Plate data being sent to view", $viewData);

                $html = view('templates.plate_dubai', $viewData)->render();
                $windowSize = [700, 500];
            }

            // Générer le fichier
            $uniqueId = time() . '_' . uniqid();
            $filename = 'plate_' . $country . '_' . $uniqueId . '.' . $format;
            $filePath = storage_path('app/public/plates/' . $filename);

            if (!file_exists(storage_path('app/public/plates'))) {
                mkdir(storage_path('app/public/plates'), 0755, true);
            }

            \Log::info("🎨 About to generate screenshot with Browsershot", [
                'html_length' => strlen($html),
                'window_size' => $windowSize,
                'file_path' => $filePath
            ]);

            // Ensure user data dir exists
            $userDataDir = storage_path('app/chrome-user-data');
            if (!file_exists($userDataDir)) {
                mkdir($userDataDir, 0755, true);
            }

            // Create temp file in storage instead of /tmp to avoid permission/isolation issues
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            // Create temp file in storage instead of /tmp to avoid permission/isolation issues
            $tempDir = storage_path('app/temp');
            if (!file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }
            
            $tempHtmlFile = $tempDir . '/plate_' . uniqid() . '.html';
            file_put_contents($tempHtmlFile, $html);
            
            // Use file:// protocol with absolute path
            $fileUrl = 'file://' . $tempHtmlFile;

            // Instantiate manually to set options BEFORE setUrl
            $browsershot = (new Browsershot())
                ->setIncludePath($tempDir) // Allow reading files in this directory
                ->setUrl($fileUrl)
                ->setNodeBinary(env('NODE_BINARY_PATH', '/home/master/.nvm/versions/node/v22.12.0/bin/node'))
                ->setNodeModulePath(base_path('node_modules'))
                ->windowSize($windowSize[0], $windowSize[1])
                ->deviceScaleFactor(3)
                ->timeout(120)
                ->noSandbox()
                ->ignoreHttpsErrors()
                ->dismissDialogs()
                ->waitUntilNetworkIdle()
                ->setOption('userDataDir', $userDataDir)
                ->setDelay(2000)
                ->showBackground()
                ->emulateMedia('screen');

            // Add arguments for stability on Cloudways
            $browsershot->addChromiumArguments([
                'no-sandbox', 
                'disable-setuid-sandbox', 
                'disable-dev-shm-usage', 
                'disable-accelerated-2d-canvas',
                'no-first-run', 
                'no-zygote', 
                'single-process', // CRITICAL for some containerized envs
                'disable-gpu'
            ]);

            // Add Chrome path if configured
            $chromePath = env('CHROME_PATH') ?? env('PUPPETEER_EXECUTABLE_PATH');
            if ($chromePath) {
                $browsershot->setOption('executablePath', $chromePath);
            } else {
                 // Fallback
                 $browsershot->setOption('executablePath', '/home/master/.cache/puppeteer/chrome/linux-143.0.7499.42/chrome-linux64/chrome');
            }

            // Save directly
            $browsershot->format($format)->save($filePath);

            // Clean up temp file
            if (file_exists($tempHtmlFile)) {
                unlink($tempHtmlFile);
            }

            \Log::info("✅ Plate file saved", [
                'filename' => $filename,
                'path' => $filePath,
                'file_exists' => file_exists($filePath),
                'file_size' => file_exists($filePath) ? filesize($filePath) : 0
            ]);

            return [
                'country' => $country,
                'filename' => $filename,
                'url' => url('storage/plates/' . $filename),
                'path' => $filePath,
                'format' => $format
            ];
        } catch (\Exception $e) {
            \Log::error('❌ Plate generation error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    private function getDefaultLogo($country)
    {
        if ($country === 'ksa') {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
                <g fill="#006c35">
                    <path d="M256 64c-16 0-32 16-32 32 0 32 16 48 32 48s32-16 32-48c0-16-16-32-32-32z"/>
                    <ellipse cx="200" cy="120" rx="60" ry="24" transform="rotate(-35 200 120)"/>
                    <ellipse cx="312" cy="120" rx="60" ry="24" transform="rotate(35 312 120)"/>
                    <rect x="246" y="100" width="20" height="180" rx="4"/>
                    <path d="M120 240c40-60 90-100 136-100s96 40 136 100c-40 30-90 50-136 50s-96-20-136-50z"/>
                </g>
            </svg>';
        } elseif ($country === 'dubai') {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="150" viewBox="0 0 200 150">
                <g fill="#000">
                    <circle cx="45" cy="90" r="25"/>
                    <circle cx="155" cy="70" r="20"/>
                    <path d="M45 90 Q80 40 120 50 Q140 55 155 70"/>
                    <path d="M155 70 Q165 50 180 45"/>
                    <path d="M120 50 Q125 35 135 30"/>
                    <ellipse cx="100" cy="60" rx="40" ry="25" transform="rotate(-20 100 60)"/>
                </g>
            </svg>';
        } else {
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100" viewBox="0 0 100 100">
                <circle cx="50" cy="50" r="45" fill="#C41E3A"/>
                <text x="50" y="60" font-size="30" fill="white" text-anchor="middle" font-weight="bold">AD</text>
            </svg>';
        }

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public function downloadPlate($filename)
    {
        $filePath = storage_path('app/public/plates/' . $filename);

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Fichier non trouvé'], 404);
        }

        return response()->download($filePath);
    }
}
