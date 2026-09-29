<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateDownloadController extends Controller
{
    public function __invoke(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        $disk = Storage::disk('private');
        abort_unless($disk->exists($certificate->pdf_path), 404);
        $stream = $disk->readStream($certificate->pdf_path);
        abort_if($stream === false, 404);

        return response()->streamDownload(function () use ($stream): void {
            fpassthru($stream);
            fclose($stream);
        }, 'certificate-'.$certificate->id.'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }
}
