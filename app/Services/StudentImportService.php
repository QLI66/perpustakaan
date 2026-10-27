<?php

namespace App\Services;

use App\Models\ClassLevel;
use App\Models\Major;
use App\Models\Member;
use App\Models\MemberType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use OpenSpout\Reader\XLSX\Options as XlsxOptions;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentImportService
{
    /**
     * Explicit mappings for the school's current workbook.
     * Dynamic mapping below also allows additional sheets that follow
     * the class/major naming convention and exist in the master data.
     */
    private const SHEET_MAPPING = [
        'X A' => ['class' => 'X',   'major' => null],
        'X B' => ['class' => 'X',   'major' => null],
        'XI A' => ['class' => 'XI',  'major' => null],
        'XI B' => ['class' => 'XI',  'major' => null],
        'XII IPA' => ['class' => 'XII', 'major' => 'IPA'],
        'XII IPS 1' => ['class' => 'XII', 'major' => 'IPS1'],
        'XII IPS 2' => ['class' => 'XII', 'major' => 'IPS2'],
    ];

    private const REQUIRED_HEADERS = ['nisn', 'nis', 'nama'];

    private const IGNORED_SHEETS = [
        'TOTAL DATA SANTRI',
    ];

    /**
     * Preview a workbook.
     *
     * Payload can be:
     * - null
     * - legacy indexed array of override rows
     * - ['overrides' => [...], 'exclude' => [...]]
     */
    public function preview(string $filePath, ?array $payload = null): array
    {
        [$overrides, $excluded] = $this->normalizePayload($payload);

        $sheetsData = $this->readAllSheets($filePath);
        $overrideMap = $this->buildOverrideMap($overrides);
        $excludedMap = $this->buildExcludedMap($excluded);

        $results = [];
        $sheetSummaries = [];

        $validCount = 0;
        $invalidCount = 0;
        $ignoredCount = 0;

        $nisnInFile = [];
        $nisInFile = [];

        foreach ($sheetsData as $sheetName => $rows) {
            $normalizedName = $this->normalizeSheetName($sheetName);

            if (
                in_array(
                    strtoupper($normalizedName),
                    self::IGNORED_SHEETS,
                    true
                )
            ) {
                continue;
            }

            $mapping = $this->resolveSheetMapping($normalizedName);

            if ($mapping === null) {
                $sheetSummaries[$sheetName] = [
                    'name' => $sheetName,
                    'student_total' => 0,
                    'valid' => 0,
                    'invalid' => 1,
                    'ignored' => 0,
                    'mapping_status' => 'UNSUPPORTED',
                ];

                $results[] = [
                    'sheet' => $sheetName,
                    'line' => null,
                    'student_number' => null,
                    'nisn' => null,
                    'nis' => null,
                    'name' => null,
                    'class' => null,
                    'major' => null,
                    'status' => 'NEEDS_FIX',
                    'is_valid' => false,
                    'ignored_reason' => null,
                    'errors' => [
                        "Sheet '{$sheetName}' tidak dikenali. "
                        .'Gunakan nama sheet sesuai pola kelas sekolah, '
                        .'misalnya X C, XI C, XII IPA, atau XII IPS 3.',
                    ],
                ];

                $invalidCount++;

                continue;
            }

            $classCode = $mapping['class'];
            $majorCode = $mapping['major'];

            $sheetSummaries[$sheetName] = [
                'name' => $sheetName,
                'student_total' => 0,
                'valid' => 0,
                'invalid' => 0,
                'ignored' => 0,
                'mapping_status' => 'MAPPED',
            ];

            $studentNumber = 0;

            foreach ($rows as $row) {
                $excelLine = (int) ($row['_excel_row'] ?? 0);

                $nisn = $this->str($row['nisn'] ?? null);
                $nis = $this->str($row['nis'] ?? null);
                $nama = $this->str($row['nama'] ?? null);

                $sourceIsIgnored = $this->isIgnorableNonStudentRow(
                    $nisn,
                    $nis,
                    $nama
                );

                $rowKey = $this->overrideKey(
                    $sheetName,
                    $excelLine
                );

                if ($sourceIsIgnored) {
                    $ignoredCount++;
                    $sheetSummaries[$sheetName]['ignored']++;

                    $results[] = [
                        'sheet' => $sheetName,
                        'line' => $excelLine,
                        'student_number' => null,
                        'nisn' => $nisn,
                        'nis' => $nis,
                        'name' => $nama,
                        'class' => $classCode,
                        'major' => $majorCode,
                        'status' => 'IGNORED',
                        'is_valid' => false,
                        'ignored_reason' => 'Baris kosong atau baris rekap Excel.',
                        'errors' => [],
                    ];

                    continue;
                }

                if (isset($excludedMap[$rowKey])) {
                    $ignoredCount++;
                    $sheetSummaries[$sheetName]['ignored']++;

                    $results[] = [
                        'sheet' => $sheetName,
                        'line' => $excelLine,
                        'student_number' => null,
                        'nisn' => $nisn,
                        'nis' => $nis,
                        'name' => $nama,
                        'class' => $classCode,
                        'major' => $majorCode,
                        'status' => 'IGNORED',
                        'is_valid' => false,
                        'ignored_reason' => 'Dihapus dari import oleh operator.',
                        'errors' => [],
                    ];

                    continue;
                }

                if (isset($overrideMap[$rowKey])) {
                    $override = $overrideMap[$rowKey];

                    $nisn = $this->str(
                        $override['nisn'] ?? $nisn
                    );

                    $nis = $this->str(
                        $override['nis'] ?? $nis
                    );

                    $nama = $this->str(
                        $override['name'] ?? $nama
                    );
                }

                $studentNumber++;
                $sheetSummaries[$sheetName]['student_total']++;

                $errors = [];

                /*
                 * Validate the class/major mapping against master data.
                 * This makes extra sheets safe: they are accepted only when
                 * their inferred class/major actually exists in the database.
                 */
                if (! ClassLevel::where('code', $classCode)->exists()) {
                    $errors[] =
                        "Kelas {$classCode} belum tersedia di master data.";
                }

                if (
                    $majorCode !== null
                    && ! Major::where('code', $majorCode)->exists()
                ) {
                    $errors[] =
                        "Jurusan {$majorCode} belum tersedia di master data.";
                }

                if ($nisn === null || $nisn === '') {
                    $errors[] = 'NISN kosong.';
                } else {
                    if (in_array($nisn, $nisnInFile, true)) {
                        $errors[] =
                            "NISN {$nisn} duplikat di dalam file.";
                    } else {
                        $nisnInFile[] = $nisn;

                        if (
                            Member::where(
                                'identity_number',
                                $nisn
                            )->exists()
                        ) {
                            $errors[] =
                                "NISN {$nisn} sudah terdaftar di sistem.";
                        }

                        if (
                            User::where(
                                'username',
                                $nisn
                            )->exists()
                        ) {
                            $errors[] =
                                "Username (NISN) {$nisn} sudah terdaftar di sistem.";
                        }
                    }
                }

                if ($nis === null || $nis === '') {
                    $errors[] = 'NIS kosong.';
                } else {
                    if (in_array($nis, $nisInFile, true)) {
                        $errors[] =
                            "NIS {$nis} duplikat di dalam file.";
                    } else {
                        $nisInFile[] = $nis;

                        if (
                            Member::where(
                                'member_number',
                                $nis
                            )->exists()
                        ) {
                            $errors[] =
                                "NIS {$nis} sudah terdaftar sebagai nomor anggota.";
                        }
                    }
                }

                if ($nama === null || $nama === '') {
                    $errors[] = 'Nama kosong.';
                }

                $isValid = count($errors) === 0;
                $status = $isValid ? 'VALID' : 'NEEDS_FIX';

                if ($isValid) {
                    $validCount++;
                    $sheetSummaries[$sheetName]['valid']++;
                } else {
                    $invalidCount++;
                    $sheetSummaries[$sheetName]['invalid']++;
                }

                $results[] = [
                    'sheet' => $sheetName,
                    'line' => $excelLine,
                    'student_number' => $studentNumber,
                    'nisn' => $nisn,
                    'nis' => $nis,
                    'name' => $nama,
                    'class' => $classCode,
                    'major' => $majorCode,
                    'status' => $status,
                    'is_valid' => $isValid,
                    'ignored_reason' => null,
                    'errors' => $errors,
                ];
            }
        }

        return [
            'total' => $validCount + $invalidCount,
            'valid' => $validCount,
            'invalid' => $invalidCount,
            'ignored' => $ignoredCount,
            'sheets' => array_values($sheetSummaries),
            'rows' => $results,
        ];
    }

    public function import(string $filePath, ?array $payload = null): array
    {

        // Bulk import: password hashing makes a large school import exceed PHP's default 30s request limit.
        set_time_limit(300);
        $preview = $this->preview($filePath, $payload);

        if ($preview['invalid'] > 0) {
            throw new \Exception(
                'Masih ada data yang perlu diperbaiki sebelum import.'
            );
        }

        $memberType = MemberType::where(
            'code',
            'STUDENT'
        )->first();

        if (! $memberType) {
            throw new \Exception(
                'Member type STUDENT tidak ditemukan.'
            );
        }

        $createdStudents = [];

        DB::beginTransaction();

        try {
            foreach ($preview['rows'] as $row) {
                if (($row['status'] ?? null) !== 'VALID') {
                    continue;
                }

                $classLevel = ClassLevel::where(
                    'code',
                    $row['class']
                )->first();

                if (! $classLevel) {
                    throw new \Exception(
                        "Kelas dengan kode '{$row['class']}' tidak ditemukan."
                    );
                }

                $major = null;

                if ($row['major'] !== null) {
                    $major = Major::where(
                        'code',
                        $row['major']
                    )->first();

                    if (! $major) {
                        throw new \Exception(
                            "Jurusan dengan kode '{$row['major']}' tidak ditemukan."
                        );
                    }
                }

                $user = User::create([
                    'name' => $row['name'],
                    'email' => null,
                    'username' => $row['nisn'],
                    'password' => Hash::make(
                        $row['nisn']
                    ),
                    'must_change_password' => true,
                ]);

                $user->assignRole('MEMBER');

                Member::create([
                    'user_id' => $user->id,
                    'member_number' => $row['nis'],
                    'name' => $row['name'],
                    'member_type_id' => $memberType->id,
                    'identity_number' => $row['nisn'],
                    'class_level_id' => $classLevel->id,
                    'major_id' => $major?->id,
                    'joined_at' => now()->format('Y-m-d'),
                    'status' => 'ACTIVE',
                    'date_of_birth' => null,
                ]);

                $createdStudents[] = [
                    'nisn' => $row['nisn'],
                    'nis' => $row['nis'],
                    'name' => $row['name'],
                    'username' => $row['nisn'],
                    'sheet' => $row['sheet'],
                    'class' => $row['class'],
                    'major' => $row['major'],
                ];
            }

            DB::commit();

            return [
                'total' => count($createdStudents),
                'created' => count($createdStudents),
                'failed' => 0,
                'students' => $createdStudents,
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    private function normalizePayload(?array $payload): array
    {
        if (! is_array($payload)) {
            return [[], []];
        }

        if (
            ! array_key_exists('overrides', $payload)
            && ! array_key_exists('exclude', $payload)
        ) {
            return [$payload, []];
        }

        $overrides = $payload['overrides'] ?? [];
        $exclude = $payload['exclude'] ?? [];

        return [
            is_array($overrides) ? $overrides : [],
            is_array($exclude) ? $exclude : [],
        ];
    }

    private function buildOverrideMap(array $overrides): array
    {
        $map = [];

        foreach ($overrides as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (
                ! array_key_exists('sheet', $row)
                || ! array_key_exists('line', $row)
            ) {
                continue;
            }

            $key = $this->overrideKey(
                (string) $row['sheet'],
                (int) $row['line']
            );

            $map[$key] = [
                'nisn' => $row['nisn'] ?? null,
                'nis' => $row['nis'] ?? null,
                'name' => $row['name'] ?? null,
            ];
        }

        return $map;
    }

    private function buildExcludedMap(array $excluded): array
    {
        $map = [];

        foreach ($excluded as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (
                ! array_key_exists('sheet', $row)
                || ! array_key_exists('line', $row)
            ) {
                continue;
            }

            $key = $this->overrideKey(
                (string) $row['sheet'],
                (int) $row['line']
            );

            $map[$key] = true;
        }

        return $map;
    }

    private function overrideKey(string $sheet, int $line): string
    {
        return $sheet.'|'.$line;
    }

    /**
     * Resolve a sheet dynamically.
     *
     * Examples:
     * X A / X B / X C -> X, no major
     * XI A / XI B / XI C -> XI, no major
     * XII IPA -> XII / IPA
     * XII IPS 1 -> XII / IPS1
     * XII IPS 3 -> XII / IPS3 (valid only when IPS3 exists in master data)
     *
     * Existing explicit mappings remain the first source of truth.
     */
    private function resolveSheetMapping(string $sheetName): ?array
    {
        if (array_key_exists($sheetName, self::SHEET_MAPPING)) {
            return self::SHEET_MAPPING[$sheetName];
        }

        $normalized = strtoupper(
            preg_replace('/\s+/', ' ', trim($sheetName))
        );

        if (preg_match('/^(X|XI)\s+[A-Z]$/', $normalized)) {
            return [
                'class' => explode(' ', $normalized, 2)[0],
                'major' => null,
            ];
        }

        if (
            preg_match(
                '/^XII\s+(.+)$/',
                $normalized,
                $matches
            )
        ) {
            $suffix = trim($matches[1]);
            $majorCode = str_replace(' ', '', $suffix);

            if ($majorCode === '') {
                return [
                    'class' => 'XII',
                    'major' => null,
                ];
            }

            return [
                'class' => 'XII',
                'major' => $majorCode,
            ];
        }

        return null;
    }

    private function isIgnorableNonStudentRow(
        ?string $nisn,
        ?string $nis,
        ?string $nama
    ): bool {
        if (
            $nisn === null
            && $nis === null
            && $nama === null
        ) {
            return true;
        }

        $values = array_values(array_filter(
            [$nisn, $nis, $nama],
            static fn ($value) => $value !== null && $value !== ''
        ));

        $hasFormula = false;
        $hasSummaryLabel = false;

        foreach ($values as $value) {
            $normalized = strtoupper(
                trim((string) $value)
            );

            if (str_starts_with($normalized, '=')) {
                $hasFormula = true;
            }

            if (str_starts_with($normalized, 'JUMLAH')) {
                $hasSummaryLabel = true;
            }
        }

        return
            ($hasFormula || $hasSummaryLabel)
            && (
                $nama === null
                || $nama === ''
                || $nisn === null
                || $nisn === ''
                || $nis === null
                || $nis === ''
            );
    }

    private function readAllSheets(string $filePath): array
    {
        $options = new XlsxOptions;
        $reader = new XlsxReader($options);
        $reader->open($filePath);

        $result = [];

        foreach ($reader->getSheetIterator() as $sheet) {
            $sheetName = $sheet->getName();
            $rows = [];
            $headers = null;
            $sheetHasNonEmptyRow = false;

            foreach (
                $sheet->getRowIterator() as $rowIndex => $row
            ) {
                $rawValues = [];

                foreach ($row->getCells() as $cell) {
                    $rawValues[] = $this->cellToString(
                        $cell->getValue()
                    );
                }

                $nonEmpty = array_filter(
                    $rawValues,
                    static fn ($value) => $value !== null && $value !== ''
                );

                if (empty($nonEmpty)) {
                    continue;
                }

                $sheetHasNonEmptyRow = true;

                if ($headers === null) {
                    if ($this->isHeaderRow($rawValues)) {
                        $headers = [];

                        foreach (
                            $rawValues as $idx => $value
                        ) {
                            $headers[$idx] = strtolower(
                                trim((string) $value)
                            );
                        }
                    }

                    continue;
                }

                $dataRow = [
                    '_excel_row' => $rowIndex,
                ];

                foreach (
                    $headers as $colIdx => $headerKey
                ) {
                    $dataRow[$headerKey] =
                        $rawValues[$colIdx] ?? null;
                }

                $rows[] = $dataRow;
            }

            if ($headers !== null) {
                $result[$sheetName] = $rows;
            } elseif ($sheetHasNonEmptyRow) {
                $result[$sheetName] = [];
            }
        }

        $reader->close();

        return $result;
    }

    private function isHeaderRow(array $values): bool
    {
        $normalized = array_map(
            static fn ($value) => strtolower(trim((string) $value)),
            $values
        );

        $found = 0;

        foreach (
            self::REQUIRED_HEADERS as $required
        ) {
            if (
                in_array(
                    $required,
                    $normalized,
                    true
                )
            ) {
                $found++;
            }
        }

        return $found === count(
            self::REQUIRED_HEADERS
        );
    }

    private function normalizeSheetName(string $name): string
    {
        return trim($name);
    }

    private function cellToString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (
            is_float($value)
            && floor($value) === $value
        ) {
            return (string) (int) $value;
        }

        return trim((string) $value);
    }

    private function str(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === ''
            ? null
            : $trimmed;
    }
}
