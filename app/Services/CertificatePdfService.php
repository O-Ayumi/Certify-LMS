<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Certificate;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

class CertificatePdfService
{
    public function store(Certificate $certificate, string $path): void
    {
        $tempDirectory = storage_path('app/mpdf');
        File::ensureDirectoryExists($tempDirectory);

        $pdf = new Mpdf([
            'mode' => 'ja',
            'format' => 'A4',
            'default_font' => 'sjis',
            'tempDir' => $tempDirectory,
        ]);
        $pdf->WriteHTML(view('certificates.pdf', ['certificate' => $certificate])->render());
        $contents = $pdf->Output('', Destination::STRING_RETURN);

        if (! Storage::disk('private')->put($path, $contents)) {
            throw new RuntimeException('修了証 PDF を保存できませんでした。');
        }
    }
}
