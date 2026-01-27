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
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Get;
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
                Forms\Components\Hidden::make('is_external_cal_type')->default(0)->dehydrated(false),

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
                                ->columnSpan(1)
                                ->required()
                                ->rules([
                                    fn ($record) => function (string $attribute, $value, \Closure $fail) use ($record) {
                                        if ($value === '-') {
                                            return;
                                        }

                                        $query = \App\Models\ToolType::query()->where('drawing_no', $value);
                                        
                                        if ($record) {
                                            $query->where('id', '!=', $record->getKey());
                                        }

                                        if ($query->exists()) {
                                            $fail('The drawing No. has already been taken.');
                                        }
                                    },
                                ]),

                            TextInput::make('size')
                                ->columnSpan(2)
                                ->label('ขนาด (Size Type)'),

                        Grid::make(3)->schema([
                            Textarea::make('range')
                                ->label('การใช้งาน (Range)')
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
                            ->label(fn ($livewire) => data_get($livewire->data ?? [], 'is_external_cal_type') ? 'รายการ Range' : 'รายการจุดตรวจสอบ (Points)')
                            ->reorderable(false)
                            ->itemLabel(fn (array $state, $livewire): ?string => 
                                data_get($livewire->data ?? [], 'is_external_cal_type') 
                                    ? 'Range ' . ($state['point'] ?? '?') 
                                    : 'Point ' . ($state['point'] ?? '?')
                            )
                            ->schema([
                                // --- ส่วนหัวของแต่ละตาราง (ชื่อตาราง + แนวโน้ม) ---
                        Grid::make(4)->schema([
                            TextInput::make('point')
                                ->label(fn ($livewire) => data_get($livewire->data ?? [], 'is_external_cal_type') ? 'ชื่อ Range (เช่น 1, 2, 3)' : 'ชื่อจุดตรวจสอบ (เช่น A, B, C)')
                                ->placeholder(fn ($livewire) => data_get($livewire->data ?? [], 'is_external_cal_type') ? 'ใส่ Range (1, 2...)' : 'ใส่ชื่อจุด (A, B...)')
                                ->required(),

                            Select::make('trend')
                                ->label('แนวโน้ม (Trend)')
                                // ->native(false)
                                ->options([
                                    'Bigger' => 'ใหญ่ขึ้น (Bigger)',
                                    'Smaller' => 'เล็กลง (Smaller)',
                                ])
                                ->hidden(fn ($livewire) => data_get($livewire->data ?? [], 'is_new_instruments_type') || data_get($livewire->data ?? [], 'is_external_cal_type'))
                                ->required(fn ($livewire) => !(data_get($livewire->data ?? [], 'is_new_instruments_type') || data_get($livewire->data ?? [], 'is_external_cal_type')))
                                ->dehydrated(fn ($livewire) => !(data_get($livewire->data ?? [], 'is_new_instruments_type') || data_get($livewire->data ?? [], 'is_external_cal_type'))),
                                ]),

                        // --- ส่วนไส้ใน: ฟิลด์ย่อย (STD, Major...) ---
                        // ใช้ Repeater อีกตัวซ้อนข้างใน เพื่อให้กดเพิ่มฟิลด์ได้เรื่อยๆ
                        
                        Repeater::make('specs')
                            ->label('รายการฟิลด์ตรวจสอบ')
                            ->deleteAction(fn ($action) => $action->icon('heroicon-o-minus-circle'))
                            ->schema([ 
                                // 🔥 Grid สำหรับ External Cal Type (4 columns)
                                Grid::make(4)
                                    ->visible(fn ($livewire) => data_get($livewire->data ?? [], 'is_external_cal_type'))
                                    ->schema([
                                        TextInput::make('usage')
                                            ->label('การใช้งาน')
                                            ->placeholder('ระบุการใช้งาน'),
                                        
                                        TextInput::make('cri_plus')
                                            ->label('Criteria (+)')
                                            ->numeric()
                                            ->placeholder('+0.00')
                                            ->prefix('+'),
                                        
                                        TextInput::make('cri_minus')
                                            ->label('Criteria (-)')
                                            ->numeric()
                                            ->placeholder('-0.00')
                                            ->prefix('-'),
                                        
                                        Select::make('cri_unit')
                                            ->label('Unit')
                                            ->placeholder('mm.')
                                            ->default('mm.')
                                            ->options([
                                                'mm' => 'mm.',
                                                'Degree/Lipda' => 'Degree/Lipda',
                                                'um' => 'um',
                                                'L/min' => 'L/min',
                                                '%' => '%',
                                                'kgf/cm2' => 'kgf/cm2',
                                                'sec' => 'sec',
                                                'kgf.cm' => 'kgf.cm',
                                                'kg' => 'kg',
                                                'g' => 'g',
                                                '%RH' => '%RH',
                                                '%F.S' => '%F.S',
                                                'Lux' => 'Lux',
                                                'V' => 'V',
                                                'A' => 'A',
                                                'Degree' => 'Degree',
                                            ])
                                            ->searchable()
                                            ->createOptionForm([
                                                TextInput::make('unit')
                                                    ->label('Unit ใหม่')
                                                    ->required(),
                                            ])
                                            ->createOptionUsing(function (array $data): string {
                                                return $data['unit'];
                                            }),
                                    ]),
                                
                                // 🔥 Grid สำหรับ Type อื่นๆ (3 columns)
                                Grid::make(3)
                                    ->hidden(fn ($livewire) => data_get($livewire->data ?? [], 'is_external_cal_type'))
                                    ->schema([
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

                                        TextInput::make('s_std')
                                            ->label('S STD')
                                            ->numeric()
                                            ->placeholder('0.00')
                                            ->formatStateUsing(fn ($state) => $state === null ? null : rtrim(rtrim(number_format((float)$state, 8, '.', ''), '0'), '.'))
                                            ->visible(fn (Forms\Get $get) => $get('label') === 'S')
                                            ->columnSpan(2),

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
                            ->addable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge') && !data_get($livewire->data ?? [], 'is_new_instruments_type') && !data_get($livewire->data ?? [], 'is_thread_ring_gauge') && !data_get($livewire->data ?? [], 'is_serration_ring_gauge') && !data_get($livewire->data ?? [], 'is_external_cal_type'))
                            ->deletable(fn ($livewire) => !data_get($livewire->data ?? [], 'is_snap_gauge') && !data_get($livewire->data ?? [], 'is_plug_gauge') && !data_get($livewire->data ?? [], 'is_thread_plug_gauge') && !data_get($livewire->data ?? [], 'is_serration_plug_gauge') && !data_get($livewire->data ?? [], 'is_new_instruments_type') && !data_get($livewire->data ?? [], 'is_thread_ring_gauge') && !data_get($livewire->data ?? [], 'is_serration_ring_gauge') && !data_get($livewire->data ?? [], 'is_external_cal_type'))
                            ->grid(1) // เรียงลงมาทีละบรรทัด
                            ->defaultItems(fn ($livewire) => match(true) {
                                (bool) data_get($livewire->data ?? [], 'is_kgauge') => 1,
                                (bool) data_get($livewire->data ?? [], 'is_new_instruments_type') => 2,
                                (bool) data_get($livewire->data ?? [], 'is_external_cal_type') => 1,
                                default => 0
                            })
                            ->default(fn ($livewire) => match(true) {
                                (bool) data_get($livewire->data ?? [], 'is_new_instruments_type') => [['label' => 'S'], ['label' => 'Cs']],
                                (bool) data_get($livewire->data ?? [], 'is_kgauge') => [['label' => 'STD']],
                                (bool) (data_get($livewire->data ?? [], 'is_thread_ring_gauge') || data_get($livewire->data ?? [], 'is_serration_ring_gauge')) => [['label' => 'วัดเกลียว']],
                                (bool) data_get($livewire->data ?? [], 'is_external_cal_type') => [['label' => '', 'cri_plus' => null, 'cri_minus' => null]],
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
            ->paginationPageOptions([10, 25, 50, 100, 500])
            ->deferLoading()
            ->columns([

                 ImageColumn::make('picture_path')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('code_type')
                    ->label('ID Code Type')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Type Name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->limit(50),

                TextColumn::make('instruments_count')
                    ->label('จำนวนเครื่องมือ')
                    ->counts('instruments') // นับจำนวนลูกให้อัตโนมัติ!
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color('info'),

                TextColumn::make('size')
                    ->label('ขนาด')
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: false),

                TextColumn::make('reference_doc')
                    ->label('เอกสารอ้างอิง')
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('drawing_no')
                    ->label('Drawing No')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('remark')
                    ->label('Remark')
                    ->sortable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),

                
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('has_instruments')
                    ->label('มีเครื่องมือใช้งาน')
                    ->placeholder('All')
                    ->trueLabel('ใช้งาน')
                    ->falseLabel('ไม่ใช้งาน')
                    ->native(false)
                    ->queries(
                        true: fn ($query) => $query->has('instruments'),
                        false: fn ($query) => $query->doesntHave('instruments'),
                    ),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
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