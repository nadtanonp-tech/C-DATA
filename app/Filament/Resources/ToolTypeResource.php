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
                        Grid::make(7)->schema([
                            TextInput::make('code_type')
                                ->label('รหัสประเภทเครื่องมือ (ID Code Type)')
                                ->columnSpan(2)
                                ->required()
                                ->unique(ignoreRecord: true),

                            TextInput::make('name')
                                ->label('ชื่อประเภทเครื่องมือ (Name Type)')
                                ->columnSpan(2)
                                ->required(),

                            TextInput::make('drawing_no')
                                ->label('Drawing No.')
                                ->unique(ignoreRecord: true)
                                ->columnSpan(1)
                                ->required(),

                            TextInput::make('size')
                            ->columnSpan(2)
                                ->label('ขนาด (Size Type)'),
                            
                            // TextInput::make('criteria_1')
                            //     ->label('เกณฑ์ในการยอมรับค่าบวก (Criteria 1)')
                            //     ->numeric()
                            //     ->minValue(0)
                            //     ->suffix(fn (Forms\Get $get) => $get('criteria_unit_selection') ?? '%F.S')
                            //     ->default('0.00')
                            //     ->afterStateHydrated(function (TextInput $component, $state, $record) {
                            //         if ($record && is_array($record->criteria_unit)) {
                            //             foreach ($record->criteria_unit as $item) {
                            //                 if (($item['index'] ?? 0) == 1) {
                            //                     $component->state($item['criteria_1'] ?? '0.00');
                            //                     return;
                            //                 }
                            //             }
                            //         }
                            //     })
                            //     // ตอนเซฟ (Dehydrate) ให้ยัดกลับเข้าไปใน JSON ตัวเดิม (ถ้ามี) หรือสร้างใหม่
                            //     ->dehydrated(false), 

                            // TextInput::make('criteria_2')
                            //     ->label('เกณฑ์ในการยอมรับค่าลบ (Criteria 2)')
                            //     ->numeric()
                            //     ->maxValue(0)
                            //     ->suffix(fn (Forms\Get $get) => $get('criteria_unit_selection') ?? '%F.S')
                            //     ->default('-0.00')
                            //     ->afterStateHydrated(function (TextInput $component, $state, $record) {
                            //         if ($record && is_array($record->criteria_unit)) {
                            //             foreach ($record->criteria_unit as $item) {
                            //                 if (($item['index'] ?? 0) == 1) {
                            //                     $component->state($item['criteria_2'] ?? '-0.00');
                            //                     return;
                            //                 }
                            //             }
                            //         }
                            //     })
                            //     ->dehydrated(false),

                            // เปลี่ยนชื่อ field เป็น criteria_unit_selection เพื่อไม่ให้ชนกับ column criteria_unit (ที่เป็น JSON)
                            // Select::make('criteria_unit_selection')
                            //     ->label('หน่วย (Unit)')
                            //     ->options([
                            //         '%F.S' => '%F.S',
                            //         'mm.' => 'mm.',
                            //         'kgf.cm' => 'kgf.cm', // เพิ่มหน่วยตามตัวอย่าง JSON
                            //     ])
                            //     ->default('%F.S')
                            //     ->live()
                            //     ->required()
                            //     ->afterStateHydrated(function (Select $component, $state, $record) {
                            //         if ($record && is_array($record->criteria_unit)) {
                            //             foreach ($record->criteria_unit as $item) {
                            //                 if (($item['index'] ?? 0) == 1) {
                            //                     $component->state($item['unit'] ?? '%F.S');
                            //                     return;
                            //                 }
                            //             }
                            //         }
                            //     })
                            //     ->dehydrated(false),
                        ]),
                        

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
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
                            ->schema([
                                // --- ส่วนหัวของแต่ละตาราง (ชื่อตาราง + แนวโน้ม) ---
                        Grid::make(2)->schema([
                            TextInput::make('point')
                                ->label('ชื่อจุดตรวจสอบ (เช่น A, B, C)')
                                ->required()
                                ->readOnly(fn () => request()->query('is_snap_gauge') || request()->query('is_plug_gauge') || request()->query('is_kgauge') || request()->query('is_thread_plug_gauge') || request()->query('is_thread_ring_gauge') || request()->query('is_serration_plug_gauge'))
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
                                    Select::make('label')
                                        ->label('ชื่อฟิลด์ (Field Name)')
                                        ->options([
                                            'STD' => 'STD',
                                            'Major' => 'Major',
                                            'Pitch' => 'Pitch',
                                            'Plug' => 'Plug',
                                            'วัดเกลียว' => 'วัดเกลียว',
                                            'S' => 'S', 
                                            'Cs' => 'Cs',
                                        ])
                                        ->required()
                                        ->disabled(fn () => request()->query('is_snap_gauge') || request()->query('is_plug_gauge') || request()->query('is_kgauge') || request()->query('is_thread_plug_gauge') || request()->query('is_thread_ring_gauge') || request()->query('is_serration_plug_gauge'))
                                        ->dehydrated()
                                        ->live(),

                                    TextInput::make('min')
                                        ->label('Min')
                                        ->numeric()
                                        ->placeholder('0.000')
                                        ->hidden(fn (Forms\Get $get) => $get('label') === 'วัดเกลียว'),

                                    TextInput::make('max')
                                        ->label('Max')
                                        ->numeric()
                                        ->placeholder('0.000')
                                        ->hidden(fn (Forms\Get $get) => $get('label') === 'วัดเกลียว'),
                                    
                                    TextInput::make('standard_value')
                                        ->label('ค่า Standard')
                                        ->visible(fn (Forms\Get $get) => $get('label') === 'วัดเกลียว')
                                        ->columnSpan(2),
                                ]),
                            ])
                            ->addActionLabel('เพิ่มฟิลด์ตรวจสอบ (+)')
                            ->addable(fn () => !request()->query('is_snap_gauge') && !request()->query('is_plug_gauge') && !request()->query('is_kgauge') && !request()->query('is_thread_plug_gauge') && !request()->query('is_thread_ring_gauge') && !request()->query('is_serration_plug_gauge'))
                            ->deletable(fn () => !request()->query('is_snap_gauge') && !request()->query('is_plug_gauge') && !request()->query('is_thread_plug_gauge') && !request()->query('is_thread_ring_gauge') && !request()->query('is_serration_plug_gauge'))
                            ->grid(1) // เรียงลงมาทีละบรรทัด
                            ->defaultItems(0) // ถ้าเพิ่มตารางใหม่ ให้เริ่มแบบว่างๆ
                        ])
                        ->addActionLabel('เพิ่มตารางใหม่ (เช่น E, F...)')
                        ->addable(fn () => !request()->query('is_snap_gauge') && !request()->query('is_plug_gauge') && !request()->query('is_thread_plug_gauge') && !request()->query('is_thread_ring_gauge') && !request()->query('is_serration_plug_gauge'))
                        ->deletable(fn () => !request()->query('is_snap_gauge') && !request()->query('is_plug_gauge') && !request()->query('is_thread_plug_gauge') && !request()->query('is_thread_ring_gauge') && !request()->query('is_serration_plug_gauge'))
                        ->collapsible() // ย่อเก็บได้
                        
                        // 🔥 ไฮไลท์: กำหนดค่าเริ่มต้น A, B, C, D ให้มาพร้อมเลย 🔥

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