<?php

namespace App\Services;

use Carbon\Carbon;

class ReportingOverviewService
{
    public function __construct(
        private readonly LoanReportService $loanReportService,
        private readonly FineIncidentReportService $fineIncidentReportService,
        private readonly AcquisitionStockOpnameReportService $acquisitionStockOpnameReportService,
        private readonly ReservationReportService $reservationReportService,
        private readonly MemberCatalogReportService $memberCatalogReportService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getOverview(
        ?Carbon $dateFrom = null,
        ?Carbon $dateTo = null
    ): array {
        $loanReport = $this->loanReportService->getLoanReport(
            $dateFrom,
            $dateTo
        );

        $returnReport = $this->loanReportService->getReturnReport(
            $dateFrom,
            $dateTo
        );

        $fineReport = $this->fineIncidentReportService->getFineReport(
            $dateFrom,
            $dateTo
        );

        $incidentReport = $this->fineIncidentReportService->getIncidentReport(
            $dateFrom,
            $dateTo
        );

        $acquisitionReport = $this->acquisitionStockOpnameReportService
            ->getAcquisitionReport(
                $dateFrom,
                $dateTo
            );

        $stockOpnameReport = $this->acquisitionStockOpnameReportService
            ->getStockOpnameReport(
                $dateFrom,
                $dateTo
            );

        $reservationReport = $this->reservationReportService
            ->getReservationReport(
                $dateFrom,
                $dateTo
            );

        $memberReport = $this->memberCatalogReportService
            ->getMemberReport(
                $dateFrom,
                $dateTo
            );

        $catalogReport = $this->memberCatalogReportService
            ->getCatalogReport();

        return [
            'period' => [
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],

            'reports' => [
                'loans' => $loanReport['summary'],
                'returns' => $returnReport['summary'],
                'fines' => $fineReport['summary'],
                'incidents' => $incidentReport['summary'],
                'acquisitions' => $acquisitionReport['summary'],
                'stock_opnames' => $stockOpnameReport['summary'],
                'reservations' => $reservationReport['summary'],
                'members' => $memberReport['summary'],
                'catalog' => $catalogReport['summary'],
            ],
        ];
    }
}
