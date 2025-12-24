<?php

namespace App\Filament\Resources\CalibrationThreadRingGaugeResource\Pages;

use App\Filament\Resources\CalibrationThreadRingGaugeResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCalibrationThreadRingGauge extends EditRecord
{
    protected static string $resource = CalibrationThreadRingGaugeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    /**
     * 🔥 Mutate ข้อมูลก่อนแสดงในฟอร์ม (สำหรับ Edit)
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        // โหลดข้อมูล Instrument มาแสดงในฟิลด์ Preview
        if (isset($data['instrument_id'])) {
            $instrument = \App\Models\Instrument::with('toolType', 'department')->find($data['instrument_id']);
            
            if ($instrument) {
                $data['instrument_name'] = $instrument->toolType?->name ?? '-';
                $data['instrument_size'] = $instrument->toolType?->size ?? '-';
                $data['instrument_department'] = $instrument->department?->name ?? '-';
                $data['instrument_serial'] = $instrument->serial_no ?? '-';
                $data['instrument_drawing'] = $instrument->toolType?->drawing_no ?? '-';
                
                // 🔥 ดึง standard_value จาก ToolType dimension_specs
                if ($instrument->toolType && $instrument->toolType->dimension_specs) {
                    $dimensionSpecs = $instrument->toolType->dimension_specs;
                    
                    // สร้าง map ของ standard_value ตาม point
                    $standardValueMap = [];
                    foreach ($dimensionSpecs as $spec) {
                        $point = $spec['point'] ?? null;
                        if (!$point) continue;
                        
                        if (isset($spec['specs']) && is_array($spec['specs'])) {
                            foreach ($spec['specs'] as $specItem) {
                                if (($specItem['label'] ?? '') === 'วัดเกลียว') {
                                    $standardValueMap[$point] = $specItem['standard_value'] ?? null;
                                }
                            }
                        }
                    }
                    
                    // 🔥 Update readings ใน calibration_data ด้วย standard_value จาก ToolType
                    if (isset($data['calibration_data']['readings']) && is_array($data['calibration_data']['readings'])) {
                        foreach ($data['calibration_data']['readings'] as $index => $reading) {
                            $point = $reading['point'] ?? null;
                            if ($point && isset($standardValueMap[$point])) {
                                $data['calibration_data']['readings'][$index]['standard_value'] = $standardValueMap[$point];
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
