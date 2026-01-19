<?php

namespace App\Filament\Resources\CalibrationRecordResource\Pages;

use App\Filament\Resources\CalibrationRecordResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCalibrationRecord extends CreateRecord
{
    protected static string $resource = CalibrationRecordResource::class;

    /**
     * 🔥 เปลี่ยนหัวข้อหน้าตาม type ที่เลือก
     */
    public function getTitle(): string
    {
        $type = request()->get('type', 'instrument');
        
        // Map type to display name
        $typeLabels = [
            'vernier_caliper' => 'Vernier Caliper',
            'vernier_digital' => 'Vernier Digital',
            'vernier_special' => 'Vernier Special',
            'depth_vernier' => 'Depth Vernier',
            'vernier_hight_gauge' => 'Vernier Height Gauge',
            'dial_vernier_hight_gauge' => 'Dial Vernier Height Gauge',
            'micro_meter' => 'Micro Meter',
            'dial_caliper' => 'Dial Caliper',
            'dial_indicator' => 'Dial Indicator',
            'dial_test_indicator' => 'Dial Test Indicator',
            'thickness_gauge' => 'Thickness Gauge',
            'thickness_caliper' => 'Thickness Caliper',
            'cylinder_gauge' => 'Cylinder Gauge',
            'chamfer_gauge' => 'Chamfer Gauge',
            'pressure_gauge' => 'Pressure Gauge',
        ];
        
        $label = $typeLabels[$type] ?? 'Instrument';
        
        return "Create {$label} Calibration";
    }
    
    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Save');
    }
    
    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Save & save another');
    }

    /**
     * 🔥 Redirect ไปหน้า View หลัง create สำเร็จ
     */
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }

    /**
     * 🔥 รักษา calibration_type จาก form data (Hidden field)
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // calibration_type ถูก set ใน Hidden field แล้ว
        // ใช้ค่าจาก form data แทน request parameter
        // เพราะ request()->get('type') อาจหายไปตอน submit form
        
        // ถ้าไม่มี calibration_type ให้ fallback เป็น VernierOther
        if (!isset($data['calibration_data']['calibration_type']) || empty($data['calibration_data']['calibration_type'])) {
            $data['calibration_data']['calibration_type'] = 'VernierOther';
        }
        
        return $data;
    }
}