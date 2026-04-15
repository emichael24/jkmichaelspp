<?php

namespace App\Http\Controllers;

use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $certificate->load('user');

        return Inertia::render('Certificates/Verify', [
            'certificate' => $certificate,
            'isValid'     => $certificate->isValid(),
        ]);
    }

    public function download(string $uuid)
    {
        $certificate = Certificate::where('uuid', $uuid)
            ->with(['user', 'certifiable'])
            ->firstOrFail();

        if ($certificate->user_id !== auth()->id() && !auth()->user()->hasRole('platform_admin')) {
            abort(403);
        }

        $html = view('certificates.download', [
            'certificate' => $certificate,
            'verifyUrl'   => route('certificates.verify', $certificate->uuid),
        ])->render();

        return response()->streamDownload(function () use ($html) {
            echo $html;
        }, Str::slug($certificate->title) . '-certificate.html', [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);
    }
}
