<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CalibrationRecordResource\Pages;
use App\Models\CalibrationRecord;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Illuminate\Database\Eloquent\Builder;

class CalibrationRecordResource extends Resource
{
    protected static ?string $model = CalibrationRecord::class;
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationLabel = 'บันทึกผลสอบเทียบ (Calibration)';
    protected static ?string $modelLabel = 'Calibration Record';
    protected static ?string $navigationGroup = 'Calibration Data'; // จัดกลุ่มเมนู
    protected static ?int $navigationSort = 1; // ลำดับการแสดง
    protected static ?string $slug = 'calibration-records'; // กำหนด slug สำหรับ URL
    
    // 🔒 ซ่อนออกจากเมนู (ใช้แค่ K-Gauge Resource แทน)
    protected static bool $shouldRegisterNavigation = false;


    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('instrument.code_no')->label('ID No.')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('instrument.toolType.name')->label('Instrument Name')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cal_date')->date('d/m/Y')->label('Cal Date')->sortable(),
                Tables\Columns\TextColumn::make('cal_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pass' => 'success',
                        'Fail' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('grade_result')->label('Grade')->badge(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('cal_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCalibrationRecords::route('/'),
            'create' => Pages\CreateCalibrationRecord::route('/create'),
            'edit' => Pages\EditCalibrationRecord::route('/{record}/edit'),
        ];
    }

    // --- ฟังก์ชันคำนวณเบื้องต้น (ตัวอย่าง Logic) ---
    public static function calculateResult($state, Set $set, Get $get)
    {
        // 1. ดึงค่า
        $val = floatval($state);
        $std = floatval($get('std_value'));
        
        // 2. คำนวณ (ตัวอย่างง่ายๆ)
        $error = $val - $std;
        $set('error_val', number_format($error, 3));
        $set('avg_reading', $val); // สมมติ Avg = Reading 1 ไปก่อน

        // 3. ตัดเกรด (Logic 10% ที่เราคุยกัน)
        // ต้องไปดึง Min/Max มาเทียบ (ในโค้ดจริงต้องดึง $get('min_spec'))
        // $set('grade', 'A'); 
    }
}