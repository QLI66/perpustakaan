<?php

namespace App\Services;

use Carbon\Carbon;

class ReportExportService
{
    private const REPORTS = [
        'loans',
        'returns',
        'fines',
        'incidents',
        'acquisitions',
        'stock_opnames',
        'reservations',
        'members',
        'catalog',
        'overview',
    ];

    public function __construct(
        private readonly ReportingOverviewService $overviewService
    ) {}

    public function supports(string $report): bool
    {
        return in_array($report, self::REPORTS, true);
    }

    /**
     * @return array<int, array{section:string,metric:string,value:mixed}>
     */
    public function buildRows(
        string $report,
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ): array {
        $overview = $this->overviewService->getOverview(
            $dateFrom,
            $dateTo
        );

        if ($report === 'overview') {
            return $this->flattenOverview(
                $overview['reports']
            );
        }

        if (! array_key_exists($report, $overview['reports'])) {
            return [];
        }

        return $this->flattenSection(
            $report,
            $overview['reports'][$report]
        );
    }

    /**
     * @param  array<string,mixed>  $reports
     * @return array<int,array{section:string,metric:string,value:mixed}>
     */
    private function flattenOverview(array $reports): array
    {
        $rows = [];

        foreach ($reports as $section => $data) {
            foreach ($this->flattenArray($data) as $metric => $value) {
                $rows[] = [
                    'section' => (string) $section,
                    'metric' => $metric,
                    'value' => $this->normalizeValue($value),
                ];
            }
        }

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<int,array{section:string,metric:string,value:mixed}>
     */
    private function flattenSection(
        string $section,
        array $data
    ): array {
        $rows = [];

        foreach ($this->flattenArray($data) as $metric => $value) {
            $rows[] = [
                'section' => $section,
                'metric' => $metric,
                'value' => $this->normalizeValue($value),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function flattenArray(
        array $data,
        string $prefix = ''
    ): array {
        $result = [];

        foreach ($data as $key => $value) {
            $metric = $prefix === ''
                ? (string) $key
                : $prefix.'.'.$key;

            if (is_array($value)) {
                foreach (
                    $this->flattenArray(
                        $value,
                        $metric
                    ) as $nestedKey => $nestedValue
                ) {
                    $result[$nestedKey] = $nestedValue;
                }

                continue;
            }

            $result[$metric] = $value;
        }

        return $result;
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return $value;
    }
}
