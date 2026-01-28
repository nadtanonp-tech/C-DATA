<?php

namespace App\Filament\Resources;

use App\Filament\Resources\InstrumentResource\Pages;
use App\Filament\Resources\InstrumentResource\RelationManagers;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Tables\Columns\TextColumn; // อย่าลืมบรรทัดนี้
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\FileUpload;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Set; // <--- เพิ่มตัวนี้
use Filament\Forms\Components\Repeater;
use App\Models\ToolType;
use App\Models\InstrumentStatusHistory;
use App\Filament\Resources\InstrumentResource\Widgets\InstrumentStatsWidget;
use App\Filament\Resources\InstrumentResource\RelationManagers\StatusHistoriesRelationManager;

class InstrumentResource extends Resource
{
    protected static ?string $model = Instrument::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver'; // เปลี่ยนไอคอนได้
    protected static ?string $navigationLabel = 'ทะเบียนเครื่องมือ (Instrument)';
    protected static ?string $modelLabel = 'Instrument';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['toolType']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // --- ส่วนที่ 1: ข้อมูลหลัก (Identity) ---
                Section::make('ข้อมูลทั่วไป (General Information)')
                    ->description('ข้อมูลระบุตัวตนของเครื่องมือวัด')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([ // แบ่ง 4 คอลัมน์
                            TextInput::make('code_no')
                                ->label('รหัสประจําตัวเครื่องมือ (ID Code Instrument)')
                                ->required()
                                ->columnSpan(2)
                                ->unique(ignoreRecord: true) // ห้ามซ้ำ (ยกเว้นตัวมันเองตอนแก้)
                                ->placeholder('เช่น x-xx-xxxx'),
        
                            Select::make('tool_type_id')
                                ->label('ประเภทเครื่องมือ (Type Instrument)')
                                ->relationship('toolType', 'name')
                                ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->code_type} - {$record->name} ( {$record->size} )")
                                ->searchable(['code_type', 'name'])
                                ->searchable(['code_type', 'name'])
                                ->required()
                                ->columnSpan(4)
                                ->placeholder('เลือกประเภทเครื่องมือ')
                                ->live() // เก็บไว้เพื่อ preview
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    // ทำแค่ preview เท่านั้น (ไม่ save)
                                    if ($state) {
                                        $type = ToolType::find($state);
                                        if ($type) {
                                            $set('name_preview', $type->code_type); 
                                        }
                                    } else {
                                        $set('name_preview', null);
                                    }
                                }),
                            
                            
                            ]),

                        Grid::make(6)->schema([
                            Select::make('equip_type')
                                ->label('ประเภทการใช้งาน')
                                ->options([
                                    'Working' => 'Working (ใช้งานทั่วไป)',
                                    'Master' => 'Master (เครื่องมือมาตรฐาน)',
                                ])
                                ->native(false)
                                ->columnSpan(2)
                                ->default('Working'),

                            TextInput::make('serial_no')
                                ->label('Serial No.'),

                            TextInput::make('brand')
                                ->label('ยี่ห้อ (Brand)'),
                                
                            TextInput::make('maker')
                                ->label('ผู้ผลิต/Maker'),

                            TextInput::make('asset_no')
                                ->label('Asset No. (บัญชี)'),
                            
                        ]),

                        FileUpload::make('instrument_image')
                            ->label('รูปภาพเครื่องมือ')
                            ->image() // บังคับว่าเป็นไฟล์รูปเท่านั้น
                            ->disk('public') // ✅ เก็บใน public disk เพื่อให้เข้าถึงได้จาก browser
                            ->directory('instrument-photos') // เก็บในโฟลเดอร์ชื่อนี้
                            ->visibility('public') // ให้คนทั่วไปเห็นรูปได้
                            ->imageEditor(), // (แถม) มีปุ่ม Crop/Rotate รูปให้ด้วย!
                    ]),

                // --- ส่วนที่ 2: การครอบครอง (Ownership) ---
                Section::make('ผู้รับผิดชอบและสถานที่ (Owner & Location)')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('owner_name')
                                ->label('ผู้รับผิดชอบ (Owner Name)'),
                            
                            TextInput::make('owner_id')
                                ->label('รหัสพนักงาน'),

                            // เดิม: TextInput::make('department')...

                            // ✅ เปลี่ยนเป็น:
                            Select::make('department_id')
                                ->label('แผนก (Department)')
                                ->relationship('department', 'name') // ดึงชื่อแผนกมาโชว์
                                ->searchable()
                                ->searchable()
                                ->placeholder('เลือกแผนก')
                                ->createOptionForm ([ // ✨ ปุ่มวิเศษ: กดบวกเพิ่มแผนกใหม่ได้ทันที
                                    TextInput::make('name')
                                            ->label('ชื่อแผนก')
                                            ->required()
                                            ->unique('departments', 'name'),
                                ])
                                ->editOptionForm([ // (แถม) ปุ่มแก้ไขชื่อแผนก
                                    TextInput::make('name')
                                            ->label('ชื่อแผนก')
                                            ->required(),
                                ]),
                            TextInput::make('machine_name')
                                ->label('ประจําเครื่องจักร (Machine)'),
                        ]),
                    ]),

                // --- ส่วนที่ 3: การสอบเทียบ (Calibration Info) ---
                Section::make('ข้อมูลการสอบเทียบ (Calibration Details)')
                    ->schema([
                        Grid::make(10)->schema([
                            Select::make('cal_place')
                                ->label('สถานที่สอบเทียบ')
                                ->options([
                                    'Internal' => 'Internal (ภายใน)',
                                    'External' => 'External (ภายนอก)',
                                ])
                                ->default('Internal')
                                ->columnSpan(2)
                                ->native(false)
                                ->required(),
                            
                            // ฟิลด์นี้อาจคำนวณอัตโนมัติในอนาคต แต่ตอนนี้ให้กรอกได้ก่อน
                            TextInput::make('cal_freq_months')
                                ->label('ความถี่ (เดือน)')
                                ->numeric()
                                ->columnSpan(2)
                                ->default(12)
                                ->suffix('เดือน')
                                ->required(),
                            
                            TextInput::make('range_spec')
                                ->columnSpan(2)
                                ->label('การใช้งาน (Range)'),

                            TextInput::make('percent_adj')
                                ->label('เกณฑ์ตัดเกรด (% Adjust)')
                                ->numeric()
                                ->default(10)
                                ->suffix('%')
                                ->columnSpan(2),
                            
                            ]),
                        Grid::make(10)->schema([
                            
                            Repeater::make('criteria_unit')
                                ->label('เกณฑ์การยอมรับ (Criteria)')
                                ->schema([
                                    TextInput::make('criteria_1')
                                        ->hiddenLabel()
                                        ->placeholder('Criteria บวก (+)')
                                        ->default('0.00'),
                                    TextInput::make('criteria_2')
                                        ->hiddenLabel()
                                        ->placeholder('Criteria ลบ (-)')
                                        ->default('-0.00'),
                                    TextInput::make('unit')
                                        ->hiddenLabel()
                                        ->placeholder('หน่วย')
                                        ->default('%F.S'),
                                ])
                                ->columns(3)
                                ->default([
                                    ['criteria_1' => '0.00', 'criteria_2' => '-0.00', 'unit' => '%F.S']
                                ])
                                ->maxItems(1)
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columnSpan(4),
                            
                            Textarea::make('reference_doc')
                                ->label('Reference Pressure')
                                ->columnSpan(6),
                        ]),
                    ]),

                // --- ส่วนที่ 4: สถานะและราคา (Status & Price) ---
                Section::make('สถานะและอื่นๆ')
                    ->schema([
                        Grid::make(5)->schema([
                            Select::make('status')
                                ->label('สถานะปัจจุบัน (Status)')
                                
                                ->options([
                                    'ใช้งาน' => 'Active',
                                    'Spare' => 'Spare',
                                    'ส่งซ่อม' => 'Repair',
                                    'ยกเลิก' => 'Inactive',
                                    'สูญหาย' => 'Lost',
                                ])
                                ->default('Spare')
                                ->native(false)
                                ->required(),

                            DatePicker::make('receive_date')
                                ->label('วันที่รับเข้า (Receive Date)')
                                ->displayFormat('d/m/Y')
                                ->native(false), // ใช้ปฏิทินสวยๆ   

                            TextInput::make('price')
                                ->label('Price (ราคานำเข้า)') // เอา (บาท) ออกจาก Label เพราะมี Suffix แล้ว
                                ->default(0) // ยังคง Validation ว่าต้องเป็นตัวเลข
                                ->suffix('บาท'), // ย้าย ฿ มาไว้ข้างหลังเป็น "บาท"
                            
                            Textarea::make('remark')
                                ->columnSpan(2)
                                ->label('หมายเหตุ (Remark)')
                        ]),
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

                ImageColumn::make('instrument_image')
                    ->label('Image')
                    ->disk('public')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('code_no')
                    ->label('ID Code Instrument')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('toolType.name')
                    ->label('Type Name')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), 

                TextColumn::make('toolType.code_type')
                    ->label('ID Code Type')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), // เอาเมาส์ชี้ดูชื่อเต็ม

                TextColumn::make('serial_no')
                    ->label('Serial No')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), // เอาเมาส์ชี้ดูชื่อเต็ม

                TextColumn::make('asset_no')
                    ->label('Asset No')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: false) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), // เอาเมาส์ชี้ดูชื่อเต็ม

                TextColumn::make('equip_type')
                    ->label('Equip Type')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn (string $state): string => match ($state) {
                        'Master' => 'warning',   // สีฟ้า
                        'Working' => 'info', // สีเหลือง
                        default => 'gray',
                    }),
                
                TextColumn::make('cal_place')
                    ->label('Location')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn (string $state): string => match ($state) {
                        'Internal' => 'info',   // สีฟ้า
                        'External' => 'warning', // สีเหลือง
                        default => 'gray',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->color(fn (string $state): string => match ($state) {
                    'ใช้งาน' => 'success', // สีเขียว
                    'Spare' => 'info', // สีเขียว
                    'ยกเลิก' => 'danger',  // สีแดง
                    'ส่งซ่อม' => 'warning', // สีเหลือง
                    'สูญหาย' => 'danger',
                    default => 'gray',
                    
                }),

                TextColumn::make('owner_name')
                    ->label('Owner Name')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('owner_id')
                    ->label('Owner ID')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('department.name')
                    ->label('แผนก')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('brand')
                    ->label('Brand')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('maker')
                    ->label('Maker')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('price')
                    ->label('Price')
                    ->money('THB')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('machine_name')
                    ->label('เครื่องจักร')
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('receive_date')
                    ->label('Receive Date')
                    ->date()
                    ->searchable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('remark')
                    ->label('Remark')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('range_spec')
                    ->label('Range การใช้งาน')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น

                TextColumn::make('cal_freq_months')
                    ->label('ความถี่')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options([
                        'ใช้งาน' => 'Active (ใช้งาน)',
                        'Spare' => 'Spare (สำรอง)',
                        'ยกเลิก' => 'Inactive (ยกเลิก)',
                        'ส่งซ่อม' => 'Repair (ส่งซ่อม)',
                        'สูญหาย' => 'Lost (สูญหาย)',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('equip_type')
                    ->label('ประเภทการใช้งาน')
                    ->options([
                        'Working' => 'Working (ใช้งานทั่วไป)',
                        'Master' => 'Master (เครื่องมือมาตรฐาน)',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('cal_place')
                    ->label('สถานที่สอบเทียบ')
                    ->options([
                        'Internal' => 'Internal (ภายใน)',
                        'External' => 'External (ภายนอก)',
                    ])
                    ->native(false),
                Tables\Filters\SelectFilter::make('tool_type_name')
                    ->label('Type Name')
                    ->options(fn () => \App\Models\ToolType::query()
                        ->distinct()
                        ->pluck('name', 'name')
                        ->toArray())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('toolType', function (Builder $q) use ($data) {
                            $q->where('name', $data['value']);
                        });
                    })
                    ->columnSpan(2),
                Tables\Filters\SelectFilter::make('code_type')
                    ->label('ID Code Type')
                    ->options(fn () => \App\Models\ToolType::pluck('code_type', 'code_type')->toArray())
                    ->searchable()
                    ->preload()
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }
                        return $query->whereHas('toolType', function (Builder $q) use ($data) {
                            $q->where('code_type', $data['value']);
                        });
                    }),
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('แผนก')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('receive_date')
                    ->label('วันที่รับเครื่อง')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('ตั้งแต่'),
                        Forms\Components\DatePicker::make('until')
                            ->label('ถึง'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data) : Builder{
                        return $query
                            ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('receive_date', '>=', $date))
                            ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('receive_date', '<=', $date));
                    }),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->color('gray'),
                Tables\Actions\EditAction::make()
                    ->color('warning'),
                // � ปุ่มเปลี่ยนสถานะเครื่องมือ (Custom Action) �
                Action::make('change_status')
                    ->label('Set Status') // ชื่อปุ่ม
                    ->icon('heroicon-m-wrench') // ไอคอนแก้ไข
                    ->color('info')
                    ->form([
                        Select::make('new_status')
                            ->label('เลือกสถานะใหม่')
                            ->options([
                                'ใช้งาน' => 'ใช้งาน (Active)',
                                'Spare' => 'สำรอง (Spare)',
                                'ส่งซ่อม' => 'ส่งซ่อม (Repair)',
                                'ยกเลิก' => 'ยกเลิก (Inactive)',
                                'สูญหาย' => 'สูญหาย (Lost)',
                            ])
                            ->required()
                            ->native(false)
                            ->default(fn (Instrument $record) => $record->status),
                        Textarea::make('status_reason')
                            ->label('เหตุผลในการเปลี่ยนสถานะ')
                            ->required()
                            ->rows(3)
                            ->placeholder('เช่น เสียหายซ่อมไม่ได้, สูญหาย, หมดอายุการใช้งาน, กลับมาใช้งานได้แล้ว'),
                    ])
                    ->action(function (Instrument $record, array $data) {
                        $oldStatus = $record->status;
                        $newStatus = $data['new_status'];
                        
                        // 📝 บันทึกประวัติการเปลี่ยนสถานะลงตารางใหม่
                        InstrumentStatusHistory::create([
                            'instrument_id' => $record->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'reason' => $data['status_reason'],
                            'changed_at' => now(),
                            'changed_by' => auth()->id(),
                        ]);
                        
                        // อัปเดตสถานะในตาราง instruments
                        $record->update([
                            'status' => $newStatus,
                        ]);
                    })  
                    // ข้อความยืนยันความปลอดภัย
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการเปลี่ยนสถานะเครื่องมือ')
                    ->modalDescription('คุณต้องการเปลี่ยนสถานะเครื่องมือนี้ใช่หรือไม่?')
                    ->modalSubmitActionLabel('ยืนยัน (Confirm)'),
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
            StatusHistoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstruments::route('/'),
            'create' => Pages\CreateInstrument::route('/create'),
            'view' => Pages\ViewInstrument::route('/{record}'),
            'edit' => Pages\EditInstrument::route('/{record}/edit'),
        ];
    }

    public static function getWidgets(): array
    {
        return [
            InstrumentStatsWidget::class,
        ];
    }
    
}
