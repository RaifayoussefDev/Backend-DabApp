<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;

class PlateGeneratorController extends Controller
{
    public function generatePlate(Request $request)
    {
        // 1. Valider les données
        $validated = $request->validate([
            'cell1' => 'required|string',
            'cell2' => 'required|string',
            'cell3' => 'required|string',
            'cell4' => 'required|string',
            'format' => 'nullable|in:png,jpg',
        ]);

        $format = $validated['format'] ?? 'png';

        // 2. Convertir le logo en base64
        $logoPath = storage_path('app/public/logo.png');

        if (file_exists($logoPath)) {
            $logoData = base64_encode(file_get_contents($logoPath));
            $logoBase64 = 'data:image/png;base64,' . $logoData;
        } else {
            // Logo par défaut si le fichier n'existe pas
            $logoBase64 = $this->getDefaultLogo();
        }

        // 3. Générer le HTML
        $html = view('templates.plate', [
            'cell1' => $validated['cell1'],
            'cell2' => $validated['cell2'],
            'cell3' => $validated['cell3'],
            'cell4' => strtoupper($validated['cell4']),
            'logoBase64' => $logoBase64,
        ])->render();

        // 4. Générer le fichier
        $uniqueId = time() . '_' . uniqid();
        $filename = 'plate_' . $uniqueId . '.' . $format;
        $filePath = storage_path('app/public/plates/' . $filename);

        if (!file_exists(storage_path('app/public/plates'))) {
            mkdir(storage_path('app/public/plates'), 0755, true);
        }

        try {
            Browsershot::html($html)
                ->setNodeBinary('C:\Program Files\nodejs\node.exe')
                ->setNpmBinary('C:\Program Files\nodejs\npm.cmd')
                ->windowSize(700, 350)
                ->deviceScaleFactor(3)
                ->timeout(60) // Augmenter le timeout
                ->noSandbox() // Ajouter cette option pour Windows
                ->format($format)
                ->save($filePath);

            return response()->json([
                'success' => true,
                'message' => 'Plaque générée avec succès',
                'data' => [
                    'filename' => $filename,
                    'url' => url('storage/plates/' . $filename),
                    'path' => $filePath,
                    'format' => $format
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la génération',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    private function getDefaultLogo()
    {
        // Logo SVG par défaut du blason saoudien
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="512" height="512" viewBox="0 0 512 512">
            <g fill="#006c35">
                <path d="M256 64c-16 0-32 16-32 32 0 32 16 48 32 48s32-16 32-48c0-16-16-32-32-32z"/>
                <ellipse cx="200" cy="120" rx="60" ry="24" transform="rotate(-35 200 120)"/>
                <ellipse cx="312" cy="120" rx="60" ry="24" transform="rotate(35 312 120)"/>
                <ellipse cx="180" cy="100" rx="50" ry="20" transform="rotate(-50 180 100)"/>
                <ellipse cx="332" cy="100" rx="50" ry="20" transform="rotate(50 332 100)"/>
                <rect x="246" y="100" width="20" height="180" rx="4"/>
                <path d="M120 240c40-60 90-100 136-100s96 40 136 100c-40 30-90 50-136 50s-96-20-136-50z"/>
                <path d="M100 280c50-80 100-120 156-120s106 40 156 120l-156 80-156-80z"/>
                <path d="M180 200l76 60-76 40zm152 0l-76 60 76 40z"/>
            </g>
        </svg>';

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
