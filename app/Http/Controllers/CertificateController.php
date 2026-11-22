<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Dompdf\Dompdf;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CertificateController extends Controller
{
    /**
     * My certificates.
     */
    public function index(Request $request)
    {
        $certificates = Certificate::forUser($request->user()->id)
            ->valid()
            ->with('certifiable')
            ->latest('issued_at')
            ->get();

        return Inertia::render('Certificates/Index', [
            'certificates' => $certificates,
        ]);
    }

    /**
     * Certificate detail.
     */
    public function show(string $uuid)
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();
        abort_unless($certificate->user_id === auth()->id() || auth()->user()->hasRole('platform_admin'), 403);
        $certificate->load(['user', 'certifiable']);

        return Inertia::render('Certificates/Show', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Public verification endpoint.
     */
    public function verify(string $uuid)
    {
        $certificate = Certificate::where('uuid', $uuid)->firstOrFail();
        $certificate->load('user:id,first_name,last_name');

        return Inertia::render('Certificates/Verify', [
            'certificate' => [...$certificate->only(['uuid', 'certificate_number', 'title', 'issued_at', 'expires_at']), 'user' => $certificate->user->only(['first_name', 'last_name'])],
            'isValid' => $certificate->isValid(),
        ]);
    }

    public function download(string $uuid)
    {
        $certificate = Certificate::where('uuid', $uuid)
            ->with(['user', 'certifiable'])
            ->firstOrFail();

        if ($certificate->user_id !== auth()->id() && ! auth()->user()->hasRole('platform_admin')) {
            abort(403);
        }
        abort_unless($certificate->isValid(), 403, 'This certificate is no longer valid.');

        $qr = (new Writer(new ImageRenderer(
            new RendererStyle(128),
            new SvgImageBackEnd
        )))->writeString(route('certificates.verify', $certificate->uuid));
        $pdf = new Dompdf(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false]);
        $pdf->setPaper('A4', 'landscape');
        $pdf->loadHtml(view('certificates.pdf', [
            'certificate' => $certificate,
            'verifyUrl' => route('certificates.verify', $certificate->uuid),
            'qrData' => 'data:image/svg+xml;base64,'.base64_encode($qr),
        ])->render());
        $pdf->render();

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$certificate->certificate_number.'.pdf"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
