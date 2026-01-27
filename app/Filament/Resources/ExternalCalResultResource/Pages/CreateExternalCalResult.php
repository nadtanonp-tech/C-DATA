<?php

namespace App\Filament\Resources\ExternalCalResultResource\Pages;

use App\Filament\Resources\ExternalCalResultResource;
use App\Models\Instrument;
use Filament\Resources\Pages\CreateRecord;
use App\Helpers\DashboardCacheHelper;

class CreateExternalCalResult extends CreateRecord
{
    protected static string $resource = ExternalCalResultResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // รับค่าจาก URL parameters
        $purchasingId = request()->query('purchasing_id') ?? request()->query('purchasing_record_id');
        $instrumentId = request()->query('instrument_id');
        
        $fillData = [];
        
        // 🔥 Handle purchasing data (จากปุ่ม "บันทึกผล" ใน ExternalPurchasingResource)
        if ($purchasingId) {
            $fillData['purchasing_record_id'] = $purchasingId;
            $fillData['instrument_id'] = $instrumentId;
            
            $purchasing = \App\Models\PurchasingRecord::find($purchasingId);
            if ($purchasing) {
                $fillData['purchasing_cal_place'] = $purchasing->vendor_name;
                $fillData['purchasing_send_date'] = $purchasing->send_date?->format('Y-m-d');
                if (!empty($purchasing->net_price)) {
                    $fillData['price'] = $purchasing->net_price;
                }
            }
        }
        
        // 🔥 Handle instrument data (จาก Dashboard Widget หรือ direct link)
        if ($instrumentId) {
            $fillData['instrument_id'] = $instrumentId;
            
            $instrument = Instrument::with(['toolType', 'department'])->find($instrumentId);
            if ($instrument) {
                // Set display info
                $fillData['instrument_name'] = $instrument->toolType?->name ?? '-';
                $fillData['instrument_size'] = $instrument->toolType?->size ?? '-';
                $fillData['instrument_serial'] = $instrument->serial_no ?? '-';
                $fillData['instrument_department'] = $instrument->department?->name ?? '-';
                
                // ดึงข้อมูลจาก dimension_specs ของ ToolType และแปลงเป็นรูปแบบ Repeater
                // สำหรับ External Cal ใช้เฉพาะ specs ที่มี criteria (cri_plus/cri_minus)
                $dimensionSpecs = $instrument->toolType?->dimension_specs ?? [];
                $ranges = [];
                
                foreach ($dimensionSpecs as $point) {
                    $specs = $point['specs'] ?? [];
                    foreach ($specs as $spec) {
                        // กรองเฉพาะ specs ที่มี criteria (สำหรับ External Cal)
                        if (empty($spec['cri_plus']) && empty($spec['cri_minus'])) {
                            continue;
                        }
                        
                        $ranges[] = [
                            'range_name' => $point['point'] ?? '',
                            'label' => $spec['label'] ?? '',
                            'criteria_plus' => $spec['cri_plus'] ?? null,
                            'criteria_minus' => $spec['cri_minus'] ?? null,
                            'unit' => $spec['cri_unit'] ?? 'um',
                            'error_max' => null,
                            'index' => null,
                        ];
                    }
                }
                
                // Initialize calibration_data if needed
                if (!isset($fillData['calibration_data'])) {
                    $fillData['calibration_data'] = [];
                }
                $fillData['calibration_data']['ranges'] = $ranges;
                $fillData['calibration_data']['calibration_type'] = 'ExternalCal';
            }
            
            // ดึงข้อมูลจาก Record ก่อนหน้า (ถ้ามี)
            $lastRecord = \App\Models\CalibrationRecord::where('instrument_id', $instrumentId)
                ->where('cal_place', 'External')
                ->orderBy('cal_date', 'desc')
                ->first();
                
            if ($lastRecord) {
                $fillData['last_cal_date'] = $lastRecord->cal_date?->format('Y-m-d');
                $lastCalData = $lastRecord->calibration_data ?? [];
                $lastErrorMax = $lastCalData['error_max_now'] 
                    ?? $lastCalData['ErrorMaxNow'] 
                    ?? $lastCalData['drift_rate']
                    ?? null;
                if (!isset($fillData['calibration_data'])) {
                    $fillData['calibration_data'] = [];
                }
                $fillData['calibration_data']['last_error_max'] = $lastErrorMax;
            }
        }
        
        // 🔥 Fill the form with all collected data
        if (!empty($fillData)) {
            $this->form->fill($fillData);
        }
    }
    
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
    
    /**
     * Mutate form data before creating the record
     * Remove temporary purchasing fields that shouldn't be saved to calibration_logs
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 🔥 Force set cal_place to 'External' to prevent Model boot from defaulting to 'Internal'
        $data['cal_place'] = 'External';

        // Store purchasing data temporarily for afterCreate
        $this->purchasingData = [
            'cal_place' => $data['purchasing_cal_place'] ?? null,
            'net_price' => $data['price'] ?? null,
            'send_date' => $data['purchasing_send_date'] ?? null,
        ];
        
        // Remove temporary fields that don't belong to calibration_logs table
        unset($data['purchasing_cal_place']);
        unset($data['purchasing_send_date']);
        
        return $data;
    }
    
    // Temporary storage for purchasing data
    protected array $purchasingData = [];
    
    protected function afterCreate(): void
    {
        // อัพเดท status และข้อมูลการส่งสอบเทียบของ PurchasingRecord
        if ($this->record->purchasing_record_id) {
            $purchasing = \App\Models\PurchasingRecord::find($this->record->purchasing_record_id);
            if ($purchasing) {
                $updateData = [
                    'status' => 'Completed',
                    'calibration_log_id' => $this->record->id,
                ];
                
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
                
                $purchasing->update($updateData);
            }
        }
        
        // 🔥 Clear Dashboard Cache
        DashboardCacheHelper::clearDashboardCache();
    }
}
