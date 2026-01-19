<?php

namespace App\Filament\Resources\InstrumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

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
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_status')
                    ->label('สถานะเดิม')
                    ->badge()
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
                    ->default('-')
                    ->tooltip(fn ($record) => $record->reason),
                Tables\Columns\TextColumn::make('changedByUser.name')
                    ->label('ผู้เปลี่ยน')
                    ->default('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // ไม่อนุญาตให้เพิ่มเอง - ต้องผ่านปุ่ม "แก้สถานะ" เท่านั้น
            ])
            ->actions([
                // ปุ่มลบ + Revert สถานะกลับไปเป็น old_status
                Tables\Actions\DeleteAction::make()
                    ->label('ลบ/ย้อนกลับ')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการลบและย้อนกลับสถานะ')
                    ->modalDescription(fn ($record) => "ลบรายการนี้และเปลี่ยนสถานะเครื่องมือกลับเป็น \"{$record->old_status}\" ใช่หรือไม่?")
                    ->before(function ($record) {
                        // Revert สถานะ instrument กลับไปเป็น old_status
                        $record->instrument->update([
                            'status' => $record->old_status,
                        ]);
                    }),
            ])
            ->bulkActions([
                // ไม่อนุญาตให้ลบ bulk
            ])
            ->defaultSort('changed_at', 'desc');
    }
}
