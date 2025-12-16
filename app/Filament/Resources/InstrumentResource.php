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

class InstrumentResource extends Resource
{
    protected static ?string $model = Instrument::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver'; // เปลี่ยนไอคอนได้
    protected static ?string $navigationLabel = 'ทะเบียนเครื่องมือ (Instrument)';
    protected static ?string $navigationGroup = 'Instrument Data';
    protected static ?string $modelLabel = 'Instrument';

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
                                ->preload()
                                ->required()
                                ->columnSpan(3)
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
                            
                            TextInput::make('serial_no')
                                ->label('Serial No.'),
                            ]),

                        Grid::make(4)->schema([
                            Select::make('equip_type')
                                ->label('ประเภทการใช้งาน')
                                ->options([
                                    'Working' => 'Working (ใช้งานทั่วไป)',
                                    'Master' => 'Master (เครื่องมือมาตรฐาน)',
                                ])
                                ->default('Working'),

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
                                ->preload()
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
                        Grid::make(4)->schema([
                            Select::make('cal_place')
                                ->label('สถานที่สอบเทียบ')
                                ->options([
                                    'Internal' => 'Internal (ภายใน)',
                                    'External' => 'External (ภายนอก)',
                                ])
                                ->default('Internal')
                                ->required(),

                            TextInput::make('cal_freq_months')
                                ->label('ความถี่ (เดือน)')
                                ->numeric()
                                ->default(12)
                                ->suffix('เดือน')
                                ->required(),
                            
                            // ฟิลด์นี้อาจคำนวณอัตโนมัติในอนาคต แต่ตอนนี้ให้กรอกได้ก่อน
                            DatePicker::make('next_cal_date')
                                ->label('วันครบกำหนด (Due Date)')
                                ->displayFormat('d/m/Y') // แสดงผลแบบไทยๆ
                                ->native(false), // ใช้ Datepicker สวยๆ ของ Filament

                            TextInput::make('range_spec')
                                ->label('การใช้งาน (Range)'),
                        ]),

                        Grid::make(4)->schema([
                            
                            TextInput::make('percent_adj')
                                ->label('เกณฑ์ในการตัดเกรด (Percent Adjust)')
                                ->numeric()
                                ->default(10)
                                ->suffix('%'),

                            TextInput::make('criteria_1')
                                ->label('เกณฑ์การยอมรับ (Criteria 1)')
                                ->numeric()
                                ->suffix('%F.S') // ใส่หน่วยต่อท้าย
                                ->placeholder('+0.000'),

                            // กล่องที่ 2
                            TextInput::make('criteria_2')
                                ->label('เกณฑ์การยอมรับ (Criteria 2)')
                                ->numeric()
                                ->suffix('%F.S')
                                ->placeholder('-0.000'),

                            TextInput::make('reference_doc')
                            ->label('Reference Pressure'),
                        ]),
                    ]),

                // --- ส่วนที่ 4: สถานะและราคา (Status & Price) ---
                Section::make('สถานะและอื่นๆ')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('status')
                                ->label('สถานะปัจจุบัน')
                                ->options([
                                    'ใช้งาน' => 'Active',
                                    'Spare' => 'Spare',
                                    'ยกเลิก' => 'Inactive',
                                    'ส่งซ่อม' => 'Repair',
                                    'สูญหาย' => 'Lost',
                                ])
                                ->default('Spare')
                                ->required(),

                            DatePicker::make('receive_date')
                                ->label('วันที่รับเข้า (Receive Date)')
                                ->displayFormat('d/m/Y')
                                ->native(false), // ใช้ปฏิทินสวยๆ   

                            TextInput::make('price')
                                ->label('ราคาซื้อ (บาท)')
                                ->numeric()
                                ->prefix('฿'),

                        ]),
                        Textarea::make('remark')
                            ->label('หมายเหตุ (Remark)')
                            ->columnSpanFull(), // กว้างเต็มบรรทัด
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. รูปภาพเครื่องมือ
                ImageColumn::make('instrument_image')
                    ->label('Image')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),
                // 2. รหัสทรัพย์สิน (ค้นหาได้ + ก๊อปปี้ได้)
                TextColumn::make('code_no')
                    ->label('ID Code Instrument')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                // 3. ชื่อเครื่องมือ
                TextColumn::make('toolType.name')
                    ->label('Type Name')
                    ->searchable()
                    ->limit(30) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), 

                // 4. รหัสประเภทเครื่องมือ
                TextColumn::make('toolType.code_type')
                    ->label('Code Type')
                    ->searchable()
                    ->limit(30) // ตัดคำถ้ายาวเกิน
                    ->tooltip(fn ($state) => $state), // เอาเมาส์ชี้ดูชื่อเต็ม

                // 4. ประเภท (ดึงข้ามตารางจาก ToolType)
                // TextColumn::make('toolType.name')
                //     ->label('Type')
                //     ->sortable()
                //     ->searchable()
                //     ->toggleable(), // ซ่อน/แสดงคอลัมน์ได้

                // 5. สถานที่เก็บ (ใส่สีแยก)
                TextColumn::make('equip_type')
                    ->label('Equip Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Master' => 'warning',   // สีฟ้า
                        'Working' => 'info', // สีเหลือง
                        default => 'gray',
                    }),
                
                TextColumn::make('cal_place')
                    ->label('Location')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Internal' => 'info',   // สีฟ้า
                        'External' => 'warning', // สีเหลือง
                        default => 'gray',
                    }),

                // 6. สถานะการใช้งาน
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                    'ใช้งาน' => 'success', // สีเขียว
                    'Spare' => 'info', // สีเขียว
                    'ยกเลิก' => 'danger',  // สีแดง
                    'ส่งซ่อม' => 'warning', // สีเหลือง
                    'สูญหาย' => 'danger',
                    default => 'gray',
                }),

                // 7. วันครบกำหนดสอบเทียบ (แจ้งเตือนสีแดงถ้าเลยกำหนด)
                TextColumn::make('next_cal_date')
                    ->label('Due Date')
                    ->date('d M Y')
                    ->sortable()
                    // ถ้าวันที่น้อยกว่าวันนี้ (เลยกำหนด) ให้เป็นสีแดง
                    ->color(fn ($state) => $state && $state < now() ? 'danger' : 'success'),

                // 8. ผู้รับผิดชอบ
                TextColumn::make('owner_name')
                    ->label('Owner')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true), // ซ่อนไว้ก่อนเป็นค่าเริ่มต้น
            ])
            ->filters([
                // เดี๋ยวเรามาเติมตัวกรองข้อมูลทีหลัง
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                // 🔴 ปุ่มยกเลิกเครื่องมือ (Custom Action) 🔴
                Action::make('cancel_instrument')
                    ->label('ยกเลิก') // ชื่อปุ่ม
                    ->icon('heroicon-o-x-circle') // ไอคอนกากบาท
                    ->color('danger') // สีแดง
                    ->visible(fn (Instrument $record) => $record->status !== 'ยกเลิก')
                    ->form([
                        DatePicker::make('cancellation_date')
                            ->label('วันที่ยกเลิก')
                            ->default(now()) // ค่าเริ่มต้นเป็นวันนี้
                            ->required(),
                        Textarea::make('cancel_reason')
                            ->label('เหตุผลที่ยกเลิก')
                            ->required()
                            ->rows(3)
                            ->placeholder('เช่น เสียหายซ่อมไม่ได้, สูญหาย, หมดอายุการใช้งาน'),
                    ])
                    ->action(function (Instrument $record, array $data) {
                        $record->update([
                            'status' => 'ยกเลิก', // เปลี่ยนสถานะ
                            'cancellation_date' => $data['cancellation_date'], // บันทึกวันที่
                            // เอาเหตุผลไปต่อท้ายใน Remark เดิม (จะได้ไม่ทับของเก่า)
                            'remark' => $record->remark . "\n[ยกเลิกเมื่อ " . now()->format('d/m/Y') . "]: " . $data['cancel_reason'],
                        ]);
                    })
                    // ข้อความยืนยันความปลอดภัย
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการยกเลิกเครื่องมือ')
                    ->modalDescription('คุณต้องการเปลี่ยนสถานะเครื่องมือนี้เป็น "ยกเลิก" ใช่หรือไม่?')
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListInstruments::route('/'),
            'create' => Pages\CreateInstrument::route('/create'),
            'edit' => Pages\EditInstrument::route('/{record}/edit'),
        ];
    }
    
}
