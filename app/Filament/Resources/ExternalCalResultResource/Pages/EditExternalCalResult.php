<?php

namespace App\Filament\Resources\ExternalCalResultResource\Pages;

use App\Filament\Resources\ExternalCalResultResource;
use App\Models\Instrument;
use App\Models\CalibrationRecord;
use App\Models\PurchasingRecord;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Helpers\DashboardCacheHelper;

class EditExternalCalResult extends EditRecord
{
    protected static string $resource = ExternalCalResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
    
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
            
            // Handle last_cal_date - prioritize persisted
            if (!empty($calData['last_cal_date'])) {
                try {
                    $lastCalDate = \Carbon\Carbon::parse($calData['last_cal_date']);
                    $data['last_cal_date'] = $lastCalDate->format('Y-m-d');
                    $data['last_cal_date_display'] = $lastCalDate->format('d/m/Y');
                } catch (\Exception $e) {}
            } else {
                // FALLBACK: ถ้าไม่มีใน calibration_data ให้ดึงจาก record ก่อนหน้า (สำหรับ record ใหม่หรือเก่าที่ยังไม่ได้ save แบบใหม่)
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
            
            // Fallback for freq_cal if not present (although normally calculated, this ensures consistent structure)
             if (empty($data['calibration_data']['freq_cal'])) {
                 // Logic for fetching freq_cal if needed, or leave null to be calculated by form logic
             }
        }
        
        // 🔥 Pre-fill purchasing fields จาก PurchasingRecord
        if (!empty($data['purchasing_record_id'])) {
            $purchasing = PurchasingRecord::find($data['purchasing_record_id']);
            if ($purchasing) {
                $data['purchasing_cal_place'] = $purchasing->vendor_name;
                $data['purchasing_send_date'] = $purchasing->send_date;
                // ถ้ายังไม่มี price ให้ดึงจาก purchasing_records.net_price
                if (empty($data['price']) && !empty($purchasing->net_price)) {
                    $data['price'] = $purchasing->net_price;
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Mutate form data before saving
     * Extract purchasing fields to sync later
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Store purchasing data temporarily for afterSave
        // 🔥 ใช้ price จาก calibration_logs แทน purchasing_net_price
        $this->purchasingData = [
            'cal_place' => $data['purchasing_cal_place'] ?? null,
            'net_price' => $data['price'] ?? null, // Sync price to purchasing_records.net_price
            'send_date' => $data['purchasing_send_date'] ?? null,
        ];
        
        // Remove temporary fields that don't belong to calibration_logs table
        unset($data['purchasing_cal_place']);
        unset($data['purchasing_send_date']);
        
        return $data;
    }
    
    // Temporary storage for purchasing data
    protected array $purchasingData = [];
    
    protected function afterSave(): void
    {
        // อัพเดทข้อมูลการส่งสอบเทียบของ PurchasingRecord
        if ($this->record->purchasing_record_id) {
            $purchasing = PurchasingRecord::find($this->record->purchasing_record_id);
            if ($purchasing) {
                $updateData = [];
                
                // Sync purchasing fields if provided
                if (!empty($this->purchasingData['cal_place'])) {
                    $updateData['vendor_name'] = $this->purchasingData['cal_place'];
                }
                if (!empty($this->purchasingData['net_price'])) {
                    $updateData['net_price'] = $this->purchasingData['net_price'];
                }
                if (!empty($this->purchasingData['send_date'])) {
                    $updateData['send_date'] = $this->purchasingData['send_date'];
                }
                
                if (!empty($updateData)) {
                    $purchasing->update($updateData);
                }
            }
        }
        
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    protected function afterDelete(): void
    {
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
}

