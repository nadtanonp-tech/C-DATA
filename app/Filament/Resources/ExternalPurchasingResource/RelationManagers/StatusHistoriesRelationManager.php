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
                        'danger' => 'Cancelled',
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
                        'danger' => 'Cancelled',
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
                // ปุ่มเปลี่ยนสถานะ (Set Status)
                Tables\Actions\Action::make('update_status')
                    ->label('Set Status')
                    ->icon('heroicon-m-wrench')
                    ->color('info')
                    ->form([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft (ร่าง)',
                                'Pending' => 'Pending (รอดำเนินการ)',
                                'Sent' => 'Sent (ส่งแล้ว)',
                                'Received' => 'Received (รับของแล้ว)',
                                'Completed' => 'Completed (เสร็จสิ้น)',
                                'Cancelled' => 'Cancelled (ยกเลิก)',
                            ])
                            ->required()
                            ->native(false)
                            ->default(fn (StatusHistoriesRelationManager $livewire) => $livewire->getOwnerRecord()->status),
                        Forms\Components\Textarea::make('remark')
                            ->label('หมายเหตุ (Remark)')
                            ->rows(3),
                    ])
                    ->action(function (array $data, StatusHistoriesRelationManager $livewire) {
                        $record = $livewire->getOwnerRecord();
                        $oldStatus = $record->status;
                        $newStatus = $data['status'];
                        $remark = $data['remark'] ?? null;

                        // Save History
                        \App\Models\PurchasingStatusHistory::create([
                            'purchasing_record_id' => $record->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'remark' => $remark,
                            'changed_by' => auth()->id(),
                        ]);

                        $updateData = [
                            'status' => $newStatus,
                            'remark' => $remark ?? $record->remark,
                        ];

                        if ($newStatus === 'Sent' && empty($record->send_date)) {
                            $updateData['send_date'] = now();
                        } elseif ($newStatus === 'Received' && empty($record->receive_date)) {
                            $updateData['receive_date'] = now();
                        }

                        $record->update($updateData);

                        return redirect(request()->header('Referer'));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการเปลี่ยนสถานะ')
                    ->modalDescription('คุณต้องการเปลี่ยนสถานะรายการนี้ใช่หรือไม่?')
                    ->modalSubmitActionLabel('ยืนยัน (Confirm)'),
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
                    ->modalDescription(fn ($record) => "ลบรายการนี้และเปลี่ยนสถานะกลับเป็น \"{$record->old_status}\" ใช่หรือไม่?")
                    ->action(function ($record) {
                        // Revert purchasing record status
                        $record->purchasingRecord->update([
                            'status' => $record->old_status,
                        ]);
                        
                        $record->delete();

                        return redirect(request()->header('Referer'));
                    }),
            ])
            ->bulkActions([
                // No bulk actions
            ])
            ->defaultSort('created_at', 'desc');
    }
}
