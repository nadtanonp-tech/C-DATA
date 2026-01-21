<?php

namespace App\Filament\Resources\ExternalCalResultResource\Pages;

use App\Filament\Resources\ExternalCalResultResource;
use App\Models\Instrument;
use App\Models\CalibrationRecord;
use Filament\Resources\Pages\ViewRecord;

class ViewExternalCalResult extends ViewRecord
{
    protected static string $resource = ExternalCalResultResource::class;
    
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // ดึงข้อมูล Instrument เพื่อแสดง fields
        if (!empty($data['instrument_id'])) {
            $instrument = Instrument::with(['toolType', 'department'])->find($data['instrument_id']);
            if ($instrument) {
                $data['instrument_name'] = $instrument->toolType?->name ?? '-';
                $data['instrument_size'] = $instrument->toolType?->size ?? '-';
                $data['instrument_serial'] = $instrument->serial_no ?? '-';
                $data['instrument_department'] = $instrument->department?->name ?? '-';
                
                // ดึง dimension_specs จาก ToolType
                $dimensionSpecs = $instrument->toolType?->dimension_specs ?? [];
                $toolTypeRanges = [];
                foreach ($dimensionSpecs as $point) {
                    $specs = $point['specs'] ?? [];
                    foreach ($specs as $spec) {
                        $toolTypeRanges[] = [
                            'range_name' => $point['point'] ?? '',
                            'label' => $spec['label'] ?? '',
                            'criteria_plus' => $spec['cri_plus'] ?? null,
                            'criteria_minus' => $spec['cri_minus'] ?? null,
                            'unit' => $spec['cri_unit'] ?? 'um',
                        ];
                    }
                }
                
                // Merge criteria จาก ToolType เข้ากับ ranges ที่มีอยู่ (ใช้เฉพาะ ranges ที่บันทึกไว้)
                $existingRanges = $data['calibration_data']['ranges'] ?? [];
                if (!empty($existingRanges)) {
                    // สร้าง lookup table จาก ToolType โดยใช้ range_name
                    $toolTypeByName = [];
                    foreach ($toolTypeRanges as $tr) {
                        $toolTypeByName[$tr['range_name']] = $tr;
                    }
                    
                    // Merge criteria เข้าไปใน ranges ที่มีอยู่ (ตาม range_name)
                    foreach ($existingRanges as $i => &$range) {
                        $rangeName = $range['range_name'] ?? '';
                        if (isset($toolTypeByName[$rangeName])) {
                            $range['criteria_plus'] = $range['criteria_plus'] ?? $toolTypeByName[$rangeName]['criteria_plus'];
                            $range['criteria_minus'] = $range['criteria_minus'] ?? $toolTypeByName[$rangeName]['criteria_minus'];
                            $range['unit'] = $range['unit'] ?? $toolTypeByName[$rangeName]['unit'];
                            $range['label'] = $range['label'] ?? $toolTypeByName[$rangeName]['label'];
                        }
                    }
                    unset($range);
                    $data['calibration_data']['ranges'] = $existingRanges;
                }
                // ไม่มี ranges - ไม่ต้องเพิ่มจาก ToolType (แสดงเฉพาะที่บันทึกไว้เท่านั้น)
            }
            
            // ดึง freq_cal และข้อมูลอ้างอิงจาก calibration_data ของ record ปัจจุบัน
            $calData = $data['calibration_data'] ?? [];
            
            // Prioritize persisted data matching the new form structure
            $data['calibration_data']['freq_cal'] = $calData['freq_cal'] ?? null;
            $data['calibration_data']['last_error_max'] = $calData['last_error_max'] ?? null;
            
            // ดึง last_cal_date จาก calibration_data หรือ record ก่อนหน้า
            if (!empty($calData['last_cal_date'])) {
                try {
                    $lastCalDate = \Carbon\Carbon::parse($calData['last_cal_date']);
                    $data['last_cal_date'] = $lastCalDate->format('Y-m-d');
                    $data['last_cal_date_display'] = $lastCalDate->format('d/m/Y');
                } catch (\Exception $e) {}
            } else {
                // FALLBACK: ถ้าไม่มีใน calibration_data ให้ดึงจาก record ก่อนหน้า
                $lastRecord = CalibrationRecord::where('instrument_id', $data['instrument_id'])
                    ->where('cal_place', 'External')
                    ->where('id', '!=', $this->record->id)
                    ->orderBy('cal_date', 'desc')
                    ->first();
                    
                if ($lastRecord) {
                    $data['last_cal_date'] = $lastRecord->cal_date?->format('Y-m-d');
                    $data['last_cal_date_display'] = $lastRecord->cal_date?->format('d/m/Y');
                    
                    // Fallback for last_error_max if not present in current calData
                    if (empty($data['calibration_data']['last_error_max'])) {
                         $lastCalData = $lastRecord->calibration_data ?? [];
                         $data['calibration_data']['last_error_max'] = $lastCalData['error_max_now'] ?? null;
                    }
                }
            }
        }
        
        return $data;
    }
}
