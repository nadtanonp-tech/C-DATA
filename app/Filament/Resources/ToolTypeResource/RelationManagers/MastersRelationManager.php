<?php

namespace App\Filament\Resources\ToolTypeResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;

class MastersRelationManager extends RelationManager
{
    protected static string $relationship = 'masters'; // ชื่อฟังก์ชันใน Model ToolType

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ถ้าอยากแก้ชื่อ Master ได้เลย (ปกติไม่ค่อยทำกันในหน้านี้)
                TextInput::make('name')->required()->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name') // ค้นหาจากชื่อ Master
            ->columns([
                Tables\Columns\TextColumn::make('master_code')->label('รหัส Master')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('ชื่อเครื่องมือ')->searchable(),
                Tables\Columns\TextColumn::make('size')->label('ขนาด'),
                
                // เพิ่มช่อง "จุดที่วัด (Check Point)" จากตาราง Pivot
                Tables\Columns\TextColumn::make('check_point')
                    ->label('ใช้ตรวจสอบจุด (Point)')
                    ->default('-'), 
            ])
            ->headerActions([
                // ปุ่มเลือก Master ที่มีอยู่แล้ว (Attach)
                Tables\Actions\AttachAction::make()
                    ->modalWidth('3xl')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['master_code', 'name']) // ค้นหาได้ทั้ง Code และ Name
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable(['master_code', 'name']) // ทำให้ค้นหาใน Dropdown ได้ (Client-side search)
                            ->options(function (RelationManager $livewire): array {
                                //1. หา ToolType ปัจจุบัน
                                $toolType = $livewire->getOwnerRecord();
                        
                                //2. หา ID ของ Master ที่ถูกเลือกไปแล้ว
                                $attachedIds = $toolType->masters()->pluck('masters.id')->toArray();

                                //3. ดึงเฉพาะ Master ที่ "ยังไม่อยู่ในรายการ" (whereNotIn)
                                return \App\Models\Master::whereNotIn('id', $attachedIds)
                                    ->get()
                                    ->mapWithKeys(function ($record) {
                                        return [$record->id => "{$record->master_code} - {$record->name} - {$record->size}"];
                                    })
                                    ->toArray();
                            }),
                        // เพิ่มช่องให้กรอก Check Point ตอนเลือก
                        TextInput::make('check_point')
                            ->label('ตรวจสอบจุด (Point A, B...)')    
                            ->placeholder('ระบุจุดที่ใช้ Master ตัวนี้วัด'),
                    ]),
            ])
            ->actions([
                // ปุ่มแก้ไขข้อมูลใน Pivot (เช่น แก้ Check Point)
                Tables\Actions\EditAction::make()
                    ->form([
                        TextInput::make('check_point')->label('ตรวจสอบจุด'),
                    ]),
                    
                // ปุ่มเอาออก (Detach)
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
