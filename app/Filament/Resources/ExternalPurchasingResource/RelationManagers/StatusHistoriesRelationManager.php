<?php

namespace App\Filament\Resources\ExternalPurchasingResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\PurchasingRecord;
use Filament\Tables\Actions\Action;

class StatusHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'statusHistories';

    protected static ?string $title = 'ประวัติการเปลี่ยนสถานะ';

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
                Tables\Columns\TextColumn::make('created_at')
                    ->label('วันที่เปลี่ยน')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('old_status')
                    ->label('สถานะเดิม')
                    ->badge()
                    ->colors([
                        'gray' => 'Draft',
                        'warning' => 'Pending',
                        'info' => 'Sent',
                        'success' => 'Received',
                        'primary' => 'Completed',
                    ]),
                Tables\Columns\TextColumn::make('new_status')
                    ->label('สถานะใหม่')
                    ->badge()
                    ->colors([
                        'gray' => 'Draft',
                        'warning' => 'Pending',
                        'info' => 'Sent',
                        'success' => 'Received',
                        'primary' => 'Completed',
                    ]),
                Tables\Columns\TextColumn::make('remark')
                    ->label('เหตุผล/หมายเหตุ')
                    ->limit(50)
                    ->default('-')
                    ->tooltip(fn ($record) => $record->remark),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('ผู้เปลี่ยน')
                    ->default('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // No create action
            ])
            ->actions([
                 // ปุ่มลบ + Revert สถานะกลับไปเป็น old_status
                 Tables\Actions\DeleteAction::make()
                 ->label('ลบ/ย้อนกลับ')
                 ->icon('heroicon-o-arrow-uturn-left')
                 ->color('danger')
                 ->requiresConfirmation()
                 ->modalHeading('ยืนยันการลบและย้อนกลับสถานะ')
                 ->modalDescription(fn ($record) => "ลบรายการนี้และเปลี่ยนสถานะกลับเป็น \"{$record->old_status}\" ใช่หรือไม่?")
                 ->before(function ($record) {
                     // Revert purchasing record status
                     // Note: $record is PurchasingStatusHistory
                     // $record->purchasingRecord is the parent
                     $record->purchasingRecord->update([
                         'status' => $record->old_status,
                     ]);
                 }),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }
}
