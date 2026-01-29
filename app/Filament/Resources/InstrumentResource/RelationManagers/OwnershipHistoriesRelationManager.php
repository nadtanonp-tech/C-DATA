<?php

namespace App\Filament\Resources\InstrumentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\InstrumentOwnershipHistory;
use App\Models\Instrument;
use Filament\Support\Colors\Color;

class OwnershipHistoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ownershipHistories';

    protected static ?string $title = 'ประวัติผู้รับผิดชอบ (Ownership History)';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('owner_id')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('owner_name')
                    ->maxLength(255),
                Forms\Components\Textarea::make('remark')
                    ->maxLength(65535),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('owner_id')
            ->columns([
                Tables\Columns\TextColumn::make('changed_at')
                    ->label('วันที่เปลี่ยน')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                // --- Owner ---
                Tables\Columns\TextColumn::make('old_owner_id')
                    ->label('รหัสเดิม')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('old_owner_name')
                    ->label('ชื่อเดิม')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('owner_id')
                    ->label('รหัสใหม่')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge()
                    ->color('success'),
                Tables\Columns\TextColumn::make('owner_name')
                    ->label('ชื่อใหม่')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->badge()
                    ->color('success'),
                
                // --- Department ---
                Tables\Columns\TextColumn::make('old_department_name')
                    ->label('แผนกเดิม')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('department_name')
                    ->label('แผนกใหม่')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: false),

                // --- Machine ---
                Tables\Columns\TextColumn::make('old_machine_name')
                    ->label('เครื่องจักรเดิม')
                    ->badge()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('machine_name')
                    ->label('เครื่องจักรใหม่')
                    ->badge()
                    ->color('success')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('remark')
                    ->label('เหตุผล (Remark)')
                    ->limit(50)
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('changedByUser.name')
                    ->label('ผู้เปลี่ยน')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->placeholder('-'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // ปุ่มเปลี่ยนผู้รับผิดชอบ (Custom Action)
                Tables\Actions\Action::make('change_owner')
                    ->label('Set Owner') // ชื่อปุ่ม
                    ->icon('heroicon-m-user-group') // ไอคอน
                    ->color('info')
                    ->fillForm(fn (OwnershipHistoriesRelationManager $livewire) => [
                        'owner_name' => $livewire->getOwnerRecord()->owner_name,
                        'owner_id' => $livewire->getOwnerRecord()->owner_id,
                        'department_id' => $livewire->getOwnerRecord()->department_id,
                        'machine_name' => $livewire->getOwnerRecord()->machine_name,
                    ])
                    ->form([
                        Forms\Components\Grid::make(2)->schema([
                            Forms\Components\TextInput::make('owner_name')
                                ->label('ผู้รับผิดชอบ (Owner Name)'),
                            
                            Forms\Components\TextInput::make('owner_id')
                                ->label('รหัสพนักงาน'),

                            Forms\Components\Select::make('department_id')
                                ->label('แผนก (Department)')
                                ->options(\App\Models\Department::all()->pluck('name', 'id'))
                                ->searchable()
                                ->preload()
                                ->createOptionForm([
                                    Forms\Components\TextInput::make('name')
                                        ->label('ชื่อแผนก')
                                        ->required()
                                        ->unique('departments', 'name'),
                                ]),
                            
                            Forms\Components\TextInput::make('machine_name')
                                ->label('ประจําเครื่องจักร (Machine)'),
                            
                            Forms\Components\Textarea::make('ownership_remark')
                                ->label('หมายเหตุการเปลี่ยน (Reason)')
                                ->placeholder('ระบุสาเหตุการเปลี่ยนแปลง')
                                ->columnSpan(2),
                        ]),
                    ])
                    ->action(function (array $data, OwnershipHistoriesRelationManager $livewire) {
                        // ดึงข้อมูลล่าสุดจาก DB เพื่อความชัวร์
                        $record = Instrument::find($livewire->getOwnerRecord()->id);
                        
                        // ส่งค่า remark ไปยัง Virtual Attribute (เพื่อ trigger event saved)
                        $record->ownership_remark = $data['ownership_remark'];
                        
                        // อัปเดตข้อมูล
                        $record->update([
                            'owner_name' => $data['owner_name'],
                            'owner_id' => $data['owner_id'],
                            'department_id' => $data['department_id'],
                            'machine_name' => $data['machine_name'],
                        ]);

                        // ⚡️ Refresh หน้าจอเพื่อให้ข้อมูลในฟอร์มหลักอัปเดตตาม
                        return redirect(request()->header('Referer'));
                    })
                    ->requiresConfirmation()
                    ->modalHeading('เปลี่ยนผู้รับผิดชอบ')
                    ->modalDescription('ระบบจะบันทึกประวัติการเปลี่ยนแปลงนี้โดยอัตโนมัติ')
                    ->modalSubmitActionLabel('บันทึก (Save)')
                    ->visible(fn ($livewire) => $livewire->pageClass === \App\Filament\Resources\InstrumentResource\Pages\EditInstrument::class),
            ])
            ->actions([
                // ปุ่มลบ + Revert (ย้อนกลับ)
                Tables\Actions\Action::make('revert')
                    ->label('ลบ/ย้อนกลับ')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการลบและย้อนกลับข้อมูล')
                    ->modalDescription(fn ($record) => "คุณต้องการลบประวัตินี้ และย้อนกลับข้อมูลเป็น: \n" .
                        ($record->old_owner_id ? "Owner: {$record->old_owner_name} ({$record->old_owner_id})\n" : "") .
                        ($record->old_department_name ? "Dept: {$record->old_department_name}\n" : "") .
                        ($record->old_machine_name ? "Machine: {$record->old_machine_name}" : "")
                    )
                    ->visible(fn ($livewire) => $livewire->pageClass === \App\Filament\Resources\InstrumentResource\Pages\EditInstrument::class)
                    ->action(function ($record) {
                        // 1. หา Department ID จากชื่อเดิม
                        $deptId = null;
                        if ($record->old_department_name) {
                            $dept = \App\Models\Department::where('name', $record->old_department_name)->first();
                            $deptId = $dept ? $dept->id : null;
                        }

                        // 2. Revert ข้อมูลกลับไปเป็นค่าเดิม (โดยไม่ให้สร้าง history ซ้อน)
                        Instrument::withoutEvents(function () use ($record, $deptId) {
                            $record->instrument->update([
                                'owner_id' => $record->old_owner_id,
                                'owner_name' => $record->old_owner_name,
                                'department_id' => $deptId,
                                'machine_name' => $record->old_machine_name,
                            ]);
                        });

                        // 3. ลบประวัติ
                        $record->delete();

                        // 4. Refresh หน้าจอ
                        return redirect(request()->header('Referer'));
                    }),
            ])
            ->bulkActions([
            ])
            ->defaultSort('changed_at', 'desc'); // Show latest first
    }
}
