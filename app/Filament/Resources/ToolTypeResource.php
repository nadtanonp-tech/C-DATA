<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolTypeResource\Pages;
use App\Models\ToolType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use App\Filament\Resources\ToolTypeResource\RelationManagers;


class ToolTypeResource extends Resource
{
    protected static ?string $model = ToolType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag'; // ไอคอนป้ายแท็ก
    protected static ?string $navigationGroup = 'Instrument Data'; // จัดกลุ่มเมนู
    protected static ?string $navigationLabel = 'ประเภทเครื่องมือ (Types)';
    protected static ?int $navigationSort = 2; // เรียงไว้บนสุด

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('ข้อมูลทั่วไป')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('code_type')
                                ->label('รหัสประเภทเครื่องมือ (ID Code Type)')
                                ->required()
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('ชื่อประเภทเครื่องมือ (Name Type)')
                                ->required(),

                            TextInput::make('drawing_no')
                                ->label('Drawing No.')
                                ->unique(ignoreRecord: true)
                                ->required(),
                        ]),
                        TextInput::make('size')
                                ->label('ขนาด (Size Type)'),

                        Grid::make(2)->schema([
                            Textarea::make('remark')
                                ->label('หมายเหตุ'),
                            
                            FileUpload::make('picture_path')
                                ->label('รูปภาพอ้างอิง (Drawing Reference)')
                                ->image()
                                ->directory('picture_path')
                                ->visibility('public')
                                ->imageEditor(),
                        ]),
                    ]),

                // --- ส่วนจัดการสเปค JSON (เดี๋ยวมาทำละเอียดในบทถัดไป) ---
                Section::make('สเปคขนาด (Dimension Specs)')
                    ->schema([
                        Repeater::make('dimension_specs')
                            ->label('รายการจุดตรวจสอบ (Points)')
                            ->itemLabel(fn (array $state): ?string => 'ตาราง ' . ($state['point'] ?? '?'))
                            ->schema([
                                // --- ส่วนหัวของแต่ละตาราง (ชื่อตาราง + แนวโน้ม) ---
                        Grid::make(2)->schema([
                            TextInput::make('point')
                                ->label('ชื่อตาราง / จุดตรวจสอบ (เช่น A, B, E)')
                                ->required()
                                ->placeholder('ใส่ชื่อจุด (A, B...)'),

                            Select::make('trend')
                                ->label('แนวโน้ม (Trend)')
                                ->options([
                                    'Bigger' => 'ใหญ่ขึ้น (Bigger)',
                                    'Smaller' => 'เล็กลง (Smaller)',
                                ])
                                ->required(),
                                ]),

                        // --- ส่วนไส้ใน: ฟิลด์ย่อย (STD, Major...) ---
                        // ใช้ Repeater อีกตัวซ้อนข้างใน เพื่อให้กดเพิ่มฟิลด์ได้เรื่อยๆ
                        
                        Repeater::make('specs')
                            ->label('รายการฟิลด์ตรวจสอบ')
                            ->deleteAction(fn ($action) => $action->icon('heroicon-o-minus-circle'))
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('label')
                                        ->label('ชื่อฟิลด์ (Field Name)')
                                        ->required()
                                        ->placeholder('เช่น STD, Major'),

                                    TextInput::make('min')
                                        ->label('Min')
                                        ->numeric()
                                        ->placeholder('0.000'),

                                    TextInput::make('max')
                                        ->label('Max')
                                        ->numeric()
                                        ->placeholder('0.000'),
                                ]),
                            ])
                            ->addActionLabel('เพิ่มฟิลด์ตรวจสอบ (+)') // ปุ่มกดเพิ่มฟิลด์
                            ->grid(1) // เรียงลงมาทีละบรรทัด
                            ->defaultItems(0) // ถ้าเพิ่มตารางใหม่ ให้เริ่มแบบว่างๆ
                        ])
                        ->addActionLabel('เพิ่มตารางใหม่ (เช่น E, F...)') // ปุ่มกดเพิ่มตาราง
                        ->collapsible() // ย่อเก็บได้
                        
                        // 🔥 ไฮไลท์: กำหนดค่าเริ่มต้น A, B, C, D ให้มาพร้อมเลย 🔥
                        ->default([
                            [
                                'point' => 'A',
                                'trend' => 'Smaller',
                                'specs' => [
                                    ['label' => 'STD', 'min' => null, 'max' => null],
                                    ['label' => 'Major', 'min' => null, 'max' => null],
                                    ['label' => 'Pitch', 'min' => null, 'max' => null],
                                    ['label' => 'วัดเกลียว', 'min' => null, 'max' => null],
                                ]
                            ],
                            [
                                'point' => 'B',
                                'trend' => 'Smaller',
                                'specs' => [
                                    ['label' => 'STD', 'min' => null, 'max' => null],
                                    ['label' => 'Major', 'min' => null, 'max' => null],
                                    ['label' => 'Pitch', 'min' => null, 'max' => null],
                                    ['label' => 'Plug', 'min' => null, 'max' => null], // B มี Plug
                                    ['label' => 'วัดเกลียว', 'min' => null, 'max' => null],
                                ]
                            ],
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code_type')
                    ->label('ID Code Type')
                    ->sortable()
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Type Name')
                    ->sortable()
                    ->searchable()
                    ->limit(50),

                TextColumn::make('instruments_count')
                    ->label('จำนวนเครื่องมือ')
                    ->counts('instruments') // นับจำนวนลูกให้อัตโนมัติ!
                    ->badge()
                    ->color('info'),

                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\MastersRelationManager::class, // <--- เพิ่มบรรทัดนี้
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListToolTypes::route('/'),
            'create' => Pages\CreateToolType::route('/create'),
            'edit' => Pages\EditToolType::route('/{record}/edit'),
        ];
    }
}