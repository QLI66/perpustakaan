<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportExportRequest;
use App\Services\ReportExportService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __construct(
        private readonly ReportExportService $exportService
    ) {}

    public function export(
        string $report,
        ReportExportRequest $request
    ): StreamedResponse {
        abort_unless(
            Auth::user()->can('dashboard.view'),
            403,
            'This action is unauthorized.'
        );

        abort_unless(
            $this->exportService->supports($report),
            404,
            'Report type not found.'
        );

        $dateFrom = $request->filled('date_from')
            ? Carbon::parse(
                $request->input('date_from')
            )
            : null;

        $dateTo = $request->filled('date_to')
            ? Carbon::parse(
                $request->input('date_to')
            )
            : null;

        $rows = $this->exportService->buildRows(
            $report,
            $dateFrom,
            $dateTo
        );

        $filename = sprintf(
            '%s-report-%s.csv',
            $report,
            now()->format('Ymd_His')
        );

        return response()->streamDownload(
            function () use ($rows): void {
                $handle = fopen('php://output', 'wb');

                if ($handle === false) {
                    return;
                }

                fputcsv(
                    $handle,
                    [
                        'section',
                        'metric',
                        'value',
                    ]
                );

                foreach ($rows as $row) {
                    fputcsv(
                        $handle,
                        [
                            $row['section'],
                            $row['metric'],
                            $row['value'],
                        ]
                    );
                }

                fclose($handle);
            },
            $filename,
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]
        );
    }
}
