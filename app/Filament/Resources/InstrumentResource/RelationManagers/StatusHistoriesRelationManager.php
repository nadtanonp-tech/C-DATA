<?php

namespace App\Filament\Resources\InstrumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use App\Models\InstrumentStatusHistory;
use App\Models\Instrument;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'ประวัติการเปลี่ยนสถานะ';

    protected static ?string $recordTitleAttribute = 'new_status';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('new_status')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('new_status')
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('วันที่เปลี่ยน')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_status')
                    ->label('สถานะเดิม')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn (string $state): string => match ($state) {
                        'ใช้งาน' => 'success',
                        'Spare' => 'info',
                        'ยกเลิก' => 'danger',
                        'สูญหาย' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('new_status')
                    ->label('สถานะใหม่')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn (string $state): string => match ($state) {
                        'ใช้งาน' => 'success',
                        'Spare' => 'info',
                        'ยกเลิก' => 'danger',
                        'สูญหาย' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->label('เหตุผล')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->default('-')
                    ->tooltip(fn ($record) => $record->reason),
                Tables\Columns\TextColumn::make('changedByUser.name')
                    ->label('ผู้เปลี่ยน')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->default('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // ปุ่มเปลี่ยนสถานะเครื่องมือ (Custom Action)
                Tables\Actions\Action::make('change_status')
                    ->label('Set Status') // ชื่อปุ่ม
                    ->icon('heroicon-m-wrench') // ไอคอนแก้ไข
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('new_status')
                            ->label('เลือกสถานะใหม่')
                            ->options([
                                'ใช้งาน' => 'ใช้งาน (Active)',
                                'Spare' => 'สำรอง (Spare)',
                                'ส่งซ่อม' => 'ส่งซ่อม (Repair)',
                                'ยกเลิก' => 'ยกเลิก (Inactive)',
                                'สูญหาย' => 'สูญหาย (Lost)',
                            ])
                            ->required()
                            ->native(false)
                            // default value from owner record
                            ->default(fn (StatusHistoriesRelationManager $livewire) => $livewire->getOwnerRecord()->status),
                        Forms\Components\Textarea::make('status_reason')
                            ->label('เหตุผลในการเปลี่ยนสถานะ')
                            ->required()
                            ->rows(3)
                            ->placeholder('เช่น เสียหายซ่อมไม่ได้, สูญหาย, หมดอายุการใช้งาน, กลับมาใช้งานได้แล้ว'),
                    ])
                    ->action(function (array $data, StatusHistoriesRelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        // ส่งค่าเหตุผลไปที่ Model (Virtual Attribute) เพื่อบันทึกประวัติอัตโนมัติ
                        $record->status_remark = $data['status_reason'];
                        
                        // อัปเดตสถานะ (Model Event จะทำงานและสร้าง History ให้เอง)
                        $record->update([
                            'status' => $data['new_status'],
                        ]);

                        return redirect(request()->header('Referer'));
                    })  
                    // ข้อความยืนยันความปลอดภัย
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการเปลี่ยนสถานะเครื่องมือ')
                    ->modalDescription('คุณต้องการเปลี่ยนสถานะเครื่องมือนี้ใช่หรือไม่?')
                    ->modalSubmitActionLabel('ยืนยัน (Confirm)')
                    ->visible(fn ($livewire) => $livewire->pageClass === \App\Filament\Resources\InstrumentResource\Pages\EditInstrument::class),
            ])
            ->actions([
                // ปุ่มลบ + Revert สถานะกลับไปเป็น old_status
                // ปุ่มลบ + Revert สถานะกลับไปเป็น old_status
                Tables\Actions\Action::make('revert')
                    ->label('ลบ/ย้อนกลับ')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการลบและย้อนกลับสถานะ')
                    ->modalDescription(fn ($record) => "ลบรายการนี้และเปลี่ยนสถานะเครื่องมือกลับเป็น \"{$record->old_status}\" ใช่หรือไม่?")
                    ->action(function ($record) {
                        // Revert สถานะ instrument กลับไปเป็น old_status โดยไม่ trigger event saved
                        Instrument::withoutEvents(function () use ($record) {
                            $record->instrument->update([
                                'status' => $record->old_status,
                            ]);
                        });
                        
                        $record->delete();

                        return redirect(request()->header('Referer'));
                    })
                    ->visible(fn ($livewire) => $livewire->pageClass === \App\Filament\Resources\InstrumentResource\Pages\EditInstrument::class),
            ])
            ->bulkActions([
                // ไม่อนุญาตให้ลบ bulk
            ])
            ->defaultSort('changed_at', 'desc');
    }
}
