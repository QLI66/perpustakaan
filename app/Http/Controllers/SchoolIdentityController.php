<?php

namespace App\Http\Controllers;

use App\Services\SchoolIdentityService;
use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolIdentityController extends Controller
{
    use ApiResponder;

    public function __construct(
        private readonly SchoolIdentityService $identityService
    ) {}

    /**
     * GET /api/settings/school-identity
     * Public endpoint to retrieve current school identity.
     */
    public function show(): JsonResponse
    {
        $identity = $this->identityService->getIdentity();

        return $this->successResponse($identity, 'Identitas sekolah berhasil diambil.');
    }

    /**
     * GET /api/settings/school-identity/logo
     * Public endpoint to stream the custom school logo.
     */
    public function logo(): BinaryFileResponse|Response
    {
        return $this->identityService->getLogoResponse();
    }

    /**
     * PUT /api/settings/school-identity
     * Super Admin only: update school name and theme color.
     */
    public function update(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->hasRole('SUPER_ADMIN'),
            403,
            'Aksi ini hanya diperbolehkan untuk Administrator.'
        );

        $validated = $request->validate([
            'school_name' => ['required', 'string', 'min:2', 'max:100'],
            'theme_color' => ['required', 'string', 'regex:/^#([A-Fa-f0-9]{6})$/'],
        ], [
            'school_name.required' => 'Nama sekolah wajib diisi.',
            'school_name.min' => 'Nama sekolah minimal 2 karakter.',
            'school_name.max' => 'Nama sekolah maksimal 100 karakter.',
            'theme_color.required' => 'Warna tema wajib diisi.',
            'theme_color.regex' => 'Format kode warna harus hex 6 digit (contoh: #1d4ed8).',
        ]);

        $identity = $this->identityService->updateIdentity([
            'school_name' => $validated['school_name'],
            'theme_color' => $validated['theme_color'],
        ]);

        return $this->successResponse($identity, 'Identitas sekolah berhasil diperbarui.');
    }

    /**
     * POST /api/settings/school-identity/logo
     * Super Admin only: upload a custom logo image.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        abort_unless(
            Auth::user()?->hasRole('SUPER_ADMIN'),
            403,
            'Aksi ini hanya diperbolehkan untuk Administrator.'
        );

        $request->validate([
            'logo' => [
                'required',
                'file',
                'mimes:png,jpg,jpeg,webp,svg',
                'max:2048', // 2 MB max
            ],
        ], [
            'logo.required' => 'File logo wajib diunggah.',
            'logo.file' => 'Input harus berupa file yang valid.',
            'logo.mimes' => 'Format file logo harus berupa PNG, JPG, JPEG, WEBP, atau SVG.',
            'logo.max' => 'Ukuran file logo maksimal 2 MB.',
        ]);

        $file = $request->file('logo');
        if (! $file || ! $file->isValid()) {
            return $this->errorResponse('File logo tidak valid atau rusak.', 422);
        }

        $identity = $this->identityService->uploadLogo($file);

        return $this->successResponse($identity, 'Logo sekolah berhasil diperbarui.');
    }

    /**
     * DELETE /api/settings/school-identity/logo
     * Super Admin only: delete custom logo and revert to default.
     */
    public function deleteLogo(): JsonResponse
    {
        abort_unless(
            Auth::user()?->hasRole('SUPER_ADMIN'),
            403,
            'Aksi ini hanya diperbolehkan untuk Administrator.'
        );

        $identity = $this->identityService->deleteLogo();

        return $this->successResponse($identity, 'Logo sekolah berhasil dihapus dan dikembalikan ke bawaan.');
    }
}
