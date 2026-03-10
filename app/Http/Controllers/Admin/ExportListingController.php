<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Listing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ZipArchive;
use Illuminate\Support\Str;

class ExportListingController extends Controller
{
    /**
     * Export all listings to a ZIP file containing an Excel sheet and images.
     *
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse|\Illuminate\Http\JsonResponse
     */
    public function exportZip()
    {
        try {
            $listings = Listing::with(['category', 'seller', 'images', 'motorcycle.brand', 'motorcycle.model', 'city', 'country'])->get();

            if ($listings->isEmpty()) {
                return response()->json(['message' => 'No listings found to export'], 404);
            }

            // 1. Create Excel File
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set Headers
            $headers = ['ID', 'Title', 'Category', 'Price', 'Status', 'Seller', 'City', 'Country', 'Motorcycle Brand', 'Motorcycle Model', 'Created At'];
            $sheet->fromArray($headers, NULL, 'A1');

            $row = 2;
            foreach ($listings as $listing) {
                $sheet->fromArray([
                    $listing->id,
                    $listing->title,
                    $listing->category?->name,
                    $listing->price,
                    $listing->status,
                    $listing->seller?->name,
                    $listing->city?->name,
                    $listing->country?->name,
                    $listing->motorcycle?->brand?->name,
                    $listing->motorcycle?->model?->name,
                    $listing->created_at?->toDateTimeString(),
                ], NULL, 'A' . $row);
                $row++;
            }

            $excelFileName = 'listings_' . time() . '.xlsx';
            $excelPath = storage_path('app/temp/' . $excelFileName);

            if (!file_exists(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save($excelPath);

            // 2. Create ZIP File
            $zipFileName = 'listings_export_' . time() . '.zip';
            $zipPath = storage_path('app/temp/' . $zipFileName);
            $zip = new ZipArchive;

            if ($zip->open($zipPath, ZipArchive::CREATE) === TRUE) {
                // Add Excel to ZIP
                $zip->addFile($excelPath, 'listings.xlsx');

                // Add Images to ZIP
                foreach ($listings as $listing) {
                    $listingFolder = 'images/listing_' . $listing->id;
                    $zip->addEmptyDir($listingFolder);

                    foreach ($listing->images as $index => $image) {
                        try {
                            $imageUrl = $image->image_url;
                            // Convert URL to absolute local path
                            $relativePath = str_replace(url('/storage'), '', $imageUrl);
                            $localPath = public_path('storage' . $relativePath);

                            if (file_exists($localPath)) {
                                $extension = pathinfo($localPath, PATHINFO_EXTENSION) ?: 'jpg';
                                $zip->addFile($localPath, $listingFolder . '/image_' . ($index + 1) . '.' . $extension);
                            }
                        } catch (\Exception $e) {
                            \Log::warning("Could not add image {$image->image_url} to ZIP: " . $e->getMessage());
                        }
                    }
                }
                $zip->close();
            } else {
                return response()->json(['error' => 'Could not create ZIP file'], 500);
            }

            // Cleanup Excel file after adding to ZIP
            if (file_exists($excelPath)) {
                unlink($excelPath);
            }

            // Return ZIP for download and delete after sending
            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            \Log::error('Export Listings ZIP failed: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['error' => 'Export failed', 'message' => $e->getMessage()], 500);
        }
    }
}
