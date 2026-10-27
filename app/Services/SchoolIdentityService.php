<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SchoolIdentityService
{
    private const DEFAULT_NAME = 'Perpustakaan Sekolah';

    private const DEFAULT_THEME = '#1d4ed8';

    private string $settingsDir;

    private string $settingsFile;

    public function __construct()
    {
        $this->settingsDir = storage_path('app/settings');
        $this->settingsFile = $this->settingsDir.'/school_identity.json';
        $this->ensureDirectoryExists();
    }

    /**
     * Get the active school identity settings.
     *
     * @return array{
     *     school_name: string,
     *     theme_color: string,
     *     has_custom_logo: bool,
     *     logo_url: string|null,
     *     updated_at: string|null
     * }
     */
    public function getIdentity(): array
    {
        $data = $this->readSettings();

        $logoPath = $data['logo_path'] ?? null;
        $hasCustomLogo = $logoPath !== null && File::exists($this->settingsDir.'/'.basename($logoPath));

        return [
            'school_name' => (string) ($data['school_name'] ?? self::DEFAULT_NAME),
            'theme_color' => (string) ($data['theme_color'] ?? self::DEFAULT_THEME),
            'has_custom_logo' => $hasCustomLogo,
            'logo_url' => $hasCustomLogo ? url('/api/settings/school-identity/logo') : null,
            'updated_at' => $data['updated_at'] ?? null,
        ];
    }

    /**
     * Update school name and theme color.
     *
     * @param  array{school_name: string, theme_color: string}  $payload
     * @return array{
     *     school_name: string,
     *     theme_color: string,
     *     has_custom_logo: bool,
     *     logo_url: string|null,
     *     updated_at: string|null
     * }
     */
    public function updateIdentity(array $payload): array
    {
        $data = $this->readSettings();

        $data['school_name'] = trim($payload['school_name']);
        $data['theme_color'] = strtolower(trim($payload['theme_color']));
        $data['updated_at'] = now()->toIso8601String();

        $this->writeSettings($data);

        return $this->getIdentity();
    }

    /**
     * Upload and store a new school logo file.
     *
     * @return array{
     *     school_name: string,
     *     theme_color: string,
     *     has_custom_logo: bool,
     *     logo_url: string|null,
     *     updated_at: string|null
     * }
     */
    public function uploadLogo(UploadedFile $file): array
    {
        $this->deleteExistingLogoFile();

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'png';
        $safeExtension = strtolower(preg_replace('/[^a-z0-9]/', '', $extension));
        if (! in_array($safeExtension, ['png', 'jpg', 'jpeg', 'webp', 'svg'], true)) {
            $safeExtension = 'png';
        }

        $filename = 'logo_'.time().'_'.bin2hex(random_bytes(4)).'.'.$safeExtension;
        $file->move($this->settingsDir, $filename);

        $data = $this->readSettings();
        $data['logo_path'] = $filename;
        $data['updated_at'] = now()->toIso8601String();

        $this->writeSettings($data);

        return $this->getIdentity();
    }

    /**
     * Delete the custom school logo.
     *
     * @return array{
     *     school_name: string,
     *     theme_color: string,
     *     has_custom_logo: bool,
     *     logo_url: string|null,
     *     updated_at: string|null
     * }
     */
    public function deleteLogo(): array
    {
        $this->deleteExistingLogoFile();

        $data = $this->readSettings();
        $data['logo_path'] = null;
        $data['updated_at'] = now()->toIso8601String();

        $this->writeSettings($data);

        return $this->getIdentity();
    }

    /**
     * Return BinaryFileResponse for serving the active logo image.
     */
    public function getLogoResponse(): BinaryFileResponse|Response
    {
        $data = $this->readSettings();
        $logoPath = $data['logo_path'] ?? null;

        if ($logoPath === null) {
            abort(404, 'Logo sekolah belum dikonfigurasi.');
        }

        $fullPath = $this->settingsDir.'/'.basename($logoPath);
        if (! File::exists($fullPath)) {
            abort(404, 'File logo tidak ditemukan.');
        }

        $mimeType = File::mimeType($fullPath);
        if (str_ends_with($fullPath, '.svg')) {
            $mimeType = 'image/svg+xml';
        }

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, private',
        ]);
    }

    private function ensureDirectoryExists(): void
    {
        if (! File::isDirectory($this->settingsDir)) {
            File::makeDirectory($this->settingsDir, 0755, true, true);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readSettings(): array
    {
        if (! File::exists($this->settingsFile)) {
            return [
                'school_name' => self::DEFAULT_NAME,
                'theme_color' => self::DEFAULT_THEME,
                'logo_path' => null,
                'updated_at' => null,
            ];
        }

        try {
            $content = File::get($this->settingsFile);
            $decoded = json_decode($content, true, 512, JSON_THROW_ON_ERROR);

            return is_array($decoded) ? $decoded : [];
        } catch (\Throwable) {
            return [
                'school_name' => self::DEFAULT_NAME,
                'theme_color' => self::DEFAULT_THEME,
                'logo_path' => null,
                'updated_at' => null,
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function writeSettings(array $data): void
    {
        $this->ensureDirectoryExists();
        File::put(
            $this->settingsFile,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }

    private function deleteExistingLogoFile(): void
    {
        $data = $this->readSettings();
        $oldFile = $data['logo_path'] ?? null;
        if ($oldFile) {
            $path = $this->settingsDir.'/'.basename($oldFile);
            if (File::exists($path)) {
                File::delete($path);
            }
        }
    }
}
