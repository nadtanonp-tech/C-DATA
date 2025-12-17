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
    protected static string $relationship = 'masters';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')->required()->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('master_code')->label('รหัส Master')->sortable(),
                Tables\Columns\TextColumn::make('name')->label('ชื่อเครื่องมือ')->searchable(),
                Tables\Columns\TextColumn::make('size')->label('ขนาด'),
                
                Tables\Columns\TextColumn::make('check_point')
                    ->label('ใช้ตรวจสอบจุด (Point)')
                    ->default('-'),
                
                // แก้ตรงนี้ - ใช้ accessor จาก Model
                Tables\Columns\TextColumn::make('cal_status')
                    ->label('Cal Status')
                    ->badge()
                    ->getStateUsing(function ($record) {
                        // เรียกใช้ accessor ที่สร้างไว้ใน Model
                        return $record->cal_status;
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Pass' => 'success',
                        'Reject' => 'danger',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->modalWidth('3xl')
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['master_code', 'name'])
                    ->form(fn (Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect()
                            ->searchable(['master_code', 'name'])
                            ->options(function (RelationManager $livewire): array {
                                $toolType = $livewire->getOwnerRecord();
                                $attachedIds = $toolType->masters()->pluck('masters.id')->toArray();

                                return \App\Models\Master::whereNotIn('id', $attachedIds)
                                    ->get()
                                    ->mapWithKeys(function ($record) {
                                        return [$record->id => "{$record->master_code} - {$record->name} - {$record->size}"];
                                    })
                                    ->toArray();
                            }),
                        TextInput::make('check_point')
                            ->label('ตรวจสอบจุด (Point A, B...)')    
                            ->placeholder('ระบุจุดที่ใช้ Master ตัวนี้วัด'),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->form([
                        TextInput::make('check_point')->label('ตรวจสอบจุด'),
                    ]),
                    
                Tables\Actions\DetachAction::make(),
            ]);
    }
}