<?php

namespace App\Filament\Resources\ExternalCalResultResource\Pages;

use App\Filament\Resources\ExternalCalResultResource;
use App\Models\Instrument;
use Filament\Resources\Pages\CreateRecord;

class CreateExternalCalResult extends CreateRecord
{
    protected static string $resource = ExternalCalResultResource::class;

    public function mount(): void
    {
        parent::mount();
        
        // รับค่าจาก URL parameters (จากปุ่ม "บันทึกผล" ใน ExternalPurchasingResource)
        $purchasingId = request()->query('purchasing_id');
        $instrumentId = request()->query('instrument_id');
        
        $fillData = [];
        
        if ($purchasingId) {
            $fillData['purchasing_record_id'] = $purchasingId;
            $fillData['instrument_id'] = $instrumentId;
            
            // 🔥 Pre-fill purchasing fields จาก PurchasingRecord
            $purchasing = \App\Models\PurchasingRecord::find($purchasingId);
            if ($purchasing) {
                $fillData['purchasing_cal_place'] = $purchasing->cal_place;
                $fillData['purchasing_send_date'] = $purchasing->send_date;
                // ดึง net_price มาใส่ใน price field
                if (!empty($purchasing->net_price)) {
                    $fillData['price'] = $purchasing->net_price;
                }
            }
        }
        
        // ดึงข้อมูลจาก Record ก่อนหน้า (ถ้ามี)
        if ($instrumentId) {
            $lastRecord = \App\Models\CalibrationRecord::where('instrument_id', $instrumentId)
                ->where('cal_place', 'External')
                ->orderBy('cal_date', 'desc')
                ->first();
                
            if ($lastRecord) {
                $fillData['last_cal_date'] = $lastRecord->cal_date?->format('d/m/Y');
                $fillData['last_error_max'] = $lastRecord->calibration_data['error_max_now'] ?? null;
            }
        }
        
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
        // Store purchasing data temporarily for afterCreate
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
                    $updateData['cal_place'] = $this->purchasingData['cal_place'];
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
    }
}
