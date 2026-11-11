<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ExportService
{
    /**
     * Generate a PDF from a view and data.
     */
    public function exportToPdf(string $viewPath, array $data, string $filename = 'export.pdf')
    {
        $this->injectBranding($data);
        $pdf = Pdf::loadView($viewPath, $data);
        
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    /**
     * Preview the PDF in the browser.
     */
    public function previewPdf(string $viewPath, array $data)
    {
        $this->injectBranding($data);
        $pdf = Pdf::loadView($viewPath, $data);
        
        return $pdf->stream();
    }

    /**
     * Inject organisation branding into the view data for standard letterhead support.
     */
    private function injectBranding(array &$data): void
    {
        $user = auth()->user();
        if ($user && $user->organisation_id) {
            $user->loadMissing('organisation');
            if (!isset($data['organisation'])) {
                $org = $user->organisation;
                
                // Convert to Base64 so DOMPDF handles it perfectly without path resolution issues
                if ($org->logo && \Illuminate\Support\Facades\Storage::disk('public')->exists($org->logo)) {
                    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($org->logo);
                    $content = base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($org->logo));
                    $data['logo_base64'] = "data:$mime;base64,$content";
                }
                
                if ($org->letterhead && \Illuminate\Support\Facades\Storage::disk('public')->exists($org->letterhead)) {
                    $mime = \Illuminate\Support\Facades\Storage::disk('public')->mimeType($org->letterhead);
                    $content = base64_encode(\Illuminate\Support\Facades\Storage::disk('public')->get($org->letterhead));
                    $data['letterhead_base64'] = "data:$mime;base64,$content";
                }

                $data['organisation'] = $org;
            }
        }
    }
}
