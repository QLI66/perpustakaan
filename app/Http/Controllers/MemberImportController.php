<?php

namespace App\Http\Controllers;

use App\Services\StudentImportService;
use App\Traits\ApiResponder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MemberImportController extends Controller
{
    use ApiResponder;

    protected StudentImportService $importService;

    public function __construct(
        StudentImportService $importService
    ) {
        $this->importService = $importService;
    }

    public function preview(Request $request)
    {
        abort_unless(
            Auth::user()->can('members.create'),
            403
        );

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
            'rows' => 'nullable|json',
        ]);

        try {
            $filePath = $request
                ->file('file')
                ->getRealPath();

            $payload = $request->filled('rows')
                ? json_decode(
                    $request->input('rows'),
                    true
                )
                : null;

            if (
                $request->filled('rows')
                && ! is_array($payload)
            ) {
                throw new \Exception(
                    'Payload preview tidak valid.'
                );
            }

            $previewData =
                $this->importService->preview(
                    $filePath,
                    $payload
                );

            return $this->successResponse(
                $previewData,
                'File berhasil divalidasi.'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                $e->getMessage(),
                400
            );
        }
    }

    public function import(Request $request)
    {
        abort_unless(
            Auth::user()->can('members.create'),
            403
        );

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls|max:5120',
            'rows' => 'nullable|json',
        ]);

        try {
            $filePath = $request
                ->file('file')
                ->getRealPath();

            $payload = $request->filled('rows')
                ? json_decode(
                    $request->input('rows'),
                    true
                )
                : null;

            if (
                $request->filled('rows')
                && ! is_array($payload)
            ) {
                throw new \Exception(
                    'Payload import tidak valid.'
                );
            }

            $result =
                $this->importService->import(
                    $filePath,
                    $payload
                );

            return $this->successResponse(
                $result,
                'Siswa berhasil diimport.'
            );
        } catch (\Throwable $e) {
            return $this->errorResponse(
                $e->getMessage(),
                400
            );
        }
    }
}
