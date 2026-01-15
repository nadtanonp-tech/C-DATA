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
    protected static ?string $navigationLabel = 'ประเภทเครื่องมือ (Types)';
    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // 🔥 Hidden Fields (เก็บ State จาก URL เพื่อกันหายตอน Livewire Rerender)
                Forms\Components\Hidden::make('is_kgauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_snap_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_plug_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_thread_plug_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_thread_ring_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_serration_plug_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_new_instruments_type')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_serration_ring_gauge')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_thread_plug_gauge_for_checking_fit_wear')->default(0)->dehydrated(false),
                Forms\Components\Hidden::make('is_serration_plug_gauge_for_checking_fit_wear')->default(0)->dehydrated(false),

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

                        Grid::make(3)->schema([
                            Textarea::make('range')
                                ->label('การใช้งาน (Range)')
                                ->required()
                                ->afterStateHydrated(function (Textarea $component, $state, $record) {
                                    $value = '';
                                    if ($record && is_array($record->criteria_unit)) {
                                        foreach ($record->criteria_unit as $item) {
                                            if (($item['index'] ?? 0) == 1) {
                                                $value = $item['range'] ?? '';
                                                break;
                                            }
                                        }
                                    }
                                    $component->state($value);
                                })
                                ->hidden(fn ($livewire) => data_get($livewire->data ?? [], 'is_kgauge') || data_get($livewire->data ?? [], 'is_snap_gauge') || data_get($livewire->data ?? [], 'is_plug_gauge') || data_get($livewire->data ?? [], 'is_thread_plug_gauge') || data_get($livewire->data ?? [], 'is_thread_ring_gauge') || data_get($livewire->data ?? [], 'is_serration_plug_gauge') || data_get($livewire->data ?? [], 'is_serration_ring_gauge') || data_get($livewire->data ?? [], 'is_thread_plug_gauge_for_checking_fit_wear') || data_get($livewire->data ?? [], 'is_serration_plug_gauge_for_checking_fit_wear')),

                            Textarea::make('remark')
                                ->label('หมายเหตุ (remark)'),
                    
                            Textarea::make('reference_doc')
                                ->label('Reference document'),
                            
                        ]),
                    ]),

                        Grid::make(1)->schema([
                            
                            
                            FileUpload::make('picture_path')
                                ->label('รูปภาพอ้างอิง (Drawing Reference)')
                                ->image()
                                ->directory('picture_path')
                                ->visibility('public')
                                ->columnSpan('2')
                                ->imageEditor(),
                        ]),
                    ]),

                // --- ส่วนจัดการสเปค JSON (เดี๋ยวมาทำละเอียดในบทถัดไป) ---
                Section::make('สเปคขนาด (Dimension Specs)')
                    ->schema([
                        Repeater::make('dimension_specs')
                            ->label('รายการจุดตรวจสอบ (Points)')
                            ->reorderable(false) // ปิดปุ่ม Move ตาม Request
                            ->itemLabel(fn (array $state): ?string => 'Point ' . ($state['point'] ?? '?'))
                            ->schema([
                                // --- ส่วนหัวของแต่ละตาราง (ชื่อตาราง + แนวโน้ม) ---
                        Grid::make(4)->schema([
                            TextInput::make('point')
                                ->label('ชื่อจุดตรวจสอบ (เช่น A, B, C)')
                                ->required()
                                ->placeholder('ใส่ชื่อจุด (A, B...)'),

                            Select::make('trend')
                                ->label('แนวโน้ม (Trend)')
                                // ->native(false)
                                ->options([
                                    'Bigger' => 'ใหญ่ขึ้น (Bigger)',
                                    'Smaller' => 'เล็กลง (Smaller)',
                                ])
                                ->disabled(fn ($livewire) => data_get($livewire->data ?? [], 'is_new_instruments_type'))
                                ->required(fn ($livewire) => !(data_get($livewire->data ?? [], 'is_new_instruments_type')))
                                ->dehydrated(fn ($livewire) => !data_get($livewire->data ?? [], 'is_new_instruments_type')),
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
                                        ->native(false)
                                        ->default('STD')
                                        ->disabled(fn ($livewire) => data_get($livewire->data ?? [], 'is_snap_gauge') || data_get($livewire->data ?? [], 'is_plug_gauge') || data_get($livewire->data ?? [], 'is_thread_plug_gauge') || data_get($livewire->data ?? [], 'is_thread_ring_gauge') || data_get($livewire->data ?? [], 'is_serration_plug_gauge') || data_get($livewire->data ?? [], 'is_serration_ring_gauge') || data_get($livewire->data ?? [], 'is_new_instruments_type'))
                                        ->dehydrated()
                                        ->live(),

                                    TextInput::make('min')
                                        ->label('Min')
                                        ->numeric()
                                        ->placeholder('0.000')
                                        ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float)$state, 8, '.', ''), '0'), '.'))
                                        ->hidden(fn (Forms\Get $get) => in_array($get('label'), ['วัดเกลียว', 'S', 'Cs'])),

                                    TextInput::make('max')
                                        ->label('Max')
                                        ->numeric()
                                        ->placeholder('0.000')
                                        ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float)$state, 8, '.', ''), '0'), '.'))
                                        ->hidden(fn (Forms\Get $get) => in_array($get('label'), ['วัดเกลียว', 'S', 'Cs'])),
                                    
                                    TextInput::make('standard_value')
                                        ->label('ค่า Standard')
                                        ->visible(fn (Forms\Get $get) => $get('label') === 'วัดเกลียว')
                                        ->columnSpan(2),

                                    // ฟิลด์สำหรับ S (0.00)
                                    TextInput::make('s_std')
                                        ->label('S STD')
                                        ->numeric()
                                        ->placeholder('0.00')
                                        ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float)$state, 8, '.', ''), '0'), '.'))
                                        // ->mask('99.99') // อาจใช้ mask ก็ได้ถ้าต้องการบังคับ format เป๊ะๆ
                                        ->visible(fn (Forms\Get $get) => $get('label') === 'S')
                                        ->columnSpan(2),

                                    // ฟิลด์สำหรับ Cs (0.000)
                                    TextInput::make('cs_std')
                                        ->label('Cs STD')
                                        ->numeric()
                                        ->placeholder('0.000')
                                        ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float)$state, 8, '.', ''), '0'), '.'))
                                        ->visible(fn (Forms\Get $get) => $get('label') === 'Cs')
                                        ->columnSpan(2),
                                ]),
                            ])
                            ->addActionLabel('เพิ่มฟิลด์ตรวจสอบ (+)')
                            ->addable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge') && !data_get($livewire->data ?? [], 'is_new_instruments_type') && !data_get($livewire->data ?? [], 'is_thread_ring_gauge') && !data_get($livewire->data ?? [], 'is_serration_ring_gauge'))
                            ->deletable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge') && !data_get($livewire->data ?? [], 'is_new_instruments_type') && !data_get($livewire->data ?? [], 'is_thread_ring_gauge') && !data_get($livewire->data ?? [], 'is_serration_ring_gauge'))
                            ->grid(1) // เรียงลงมาทีละบรรทัด
                            ->defaultItems(fn ($livewire) => data_get($livewire->data ?? [], 'is_kgauge') ? 1 : (data_get($livewire->data ?? [], 'is_new_instruments_type') ? 2 : 0))
                            ->default(fn ($livewire) => match(true) {
                                (bool) data_get($livewire->data ?? [], 'is_new_instruments_type') => [['label' => 'S'], ['label' => 'Cs']],
                                (bool) data_get($livewire->data ?? [], 'is_kgauge') => [['label' => 'STD']],
                                (bool) (data_get($livewire->data ?? [], 'is_thread_ring_gauge') || data_get($livewire->data ?? [], 'is_serration_ring_gauge')) => [['label' => 'วัดเกลียว']],
                                default => null
                            })
                        ])
                        ->addActionLabel('เพิ่มตารางใหม่ (เช่น E, F...)')
                        ->addable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge'))
                        ->deletable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge'))
                        ->collapsible() // ย่อเก็บได้
                        
                        // 🔥 ไฮไลท์: กำหนดค่าเริ่มต้น A, B, C, D ให้มาพร้อมเลย 🔥

                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25])
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
                Tables\Actions\EditAction::make()
                    ->color('warning'),
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