<?php

namespace App\Http\Controllers;

use App\Models\MonthlyPlan;
use App\Models\Instrument;
use App\Models\CalibrationRecord;
use App\Models\ToolType;
use App\Models\Department;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class MonthlyPlanPdfController extends Controller
{
    /**
     * Generate PDF based on type
     */
    public function generate(Request $request)
    {
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $department = $request->department;
        $calibrationType = $request->calibration_type; // Changed from tool_type_id
        $status = $request->status;
        $pdfType = $request->pdf_type;

        switch ($pdfType) {
            case 'monthly_report':
                return $this->monthlyReport($startDate, $endDate, $department, $calibrationType, $status);
            case 'internal_plan':
                return $this->internalPlan($startDate, $endDate, $department, $calibrationType);
            default:
                abort(404, 'Invalid PDF type');
        }
    }

    /**
     * PDF 1: Monthly Report (ใบสรุปผลสอบเทียบประจำเดือน)
     */
    private function monthlyReport(Carbon $startDate, Carbon $endDate, $department, $calibrationType, $status = null)
    {
        $query = MonthlyPlan::whereBetween('plan_month', [$startDate, $endDate]);

        if ($department && $department !== 'all') {
            $query->where('department', $department);
        }

        if ($calibrationType && $calibrationType !== 'all') {
            $query->where('calibration_type', $calibrationType);
        }

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $data = $query->orderBy('department')->orderBy('calibration_type')->get();

        // Group by Calibration Type
        $grouped = $data->groupBy('calibration_type');

        $pdf = Pdf::loadView('pdf.monthly-report', [
            'data' => $grouped,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'department' => $department === 'all' ? 'ทั้งหมด' : $department, // Pass logic, not raw string
            'status' => $status === 'all' || !$status ? 'All' : $status, // Pass filter value for display
            'generatedAt' => now(),
        ])->setOption('isPhpEnabled', true);

        return $pdf->stream('monthly-report-' . $startDate->format('Ym') . '.pdf');
    }

    /**
     * PDF 2: Gauge/Instrument Cal Plan (แผนสอบเทียบรายละเอียด)
     */
    private function calPlan(Carbon $startDate, Carbon $endDate, $department, $toolTypeId)
    {
        $query = Instrument::with(['toolType', 'department', 'calibrationRecords' => function ($q) {
            $q->orderBy('cal_date', 'desc')->limit(1);
        }])
        ->whereBetween('next_cal_date', [$startDate, $endDate]);

        if ($department && $department !== 'all') {
            $query->whereHas('department', fn ($q) => $q->where('name', $department));
        }

        if ($toolTypeId && $toolTypeId !== 'all') {
            $query->where('tool_type_id', $toolTypeId);
        }

        $instruments = $query->orderBy('code_no')->get();

        $pdf = Pdf::loadView('pdf.cal-plan', [
            'instruments' => $instruments,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'department' => $department === 'all' ? 'ทั้งหมด' : $department,
            'toolType' => $toolTypeId === 'all' ? 'ทั้งหมด' : ToolType::find($toolTypeId)?->name,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->stream('cal-plan-' . $startDate->format('Ym') . '.pdf');
    }

    /**
     * PDF 3: Internal Calibration Plan (ใบให้หัวหน้าเซ็น)
     */
    private function internalPlan(Carbon $startDate, Carbon $endDate, $department, $calibrationType)
    {
        $query = MonthlyPlan::whereBetween('plan_month', [$startDate, $endDate]);

        if ($calibrationType && $calibrationType !== 'all') {
            $query->where('calibration_type', $calibrationType);
        }

        $data = $query->orderBy('department')->orderBy('plan_month')->get();

        // Group by month and department
        $grouped = $data->groupBy([
            fn ($item) => Carbon::parse($item->plan_month)->format('F Y'),
            fn ($item) => $item->department,
        ]);

        $pdf = Pdf::loadView('pdf.internal-plan', [
            'data' => $grouped,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'calibrationType' => $calibrationType === 'all' ? 'ทั้งหมด' : $calibrationType,
            'generatedAt' => now(),
        ]);

        return $pdf->stream('internal-plan-' . $startDate->format('Ym') . '.pdf');
    }
}
