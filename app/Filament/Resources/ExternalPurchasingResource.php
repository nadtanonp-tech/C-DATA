<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ExternalPurchasingResource\Pages;
use App\Models\PurchasingRecord;
use App\Models\Instrument;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Set;
use Filament\Forms\Get;

class ExternalPurchasingResource extends Resource
{
    protected static ?string $model = PurchasingRecord::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';
    protected static ?string $navigationLabel = 'รายการส่งสอบเทียบ (In External)';
    protected static ?string $modelLabel = 'รายการส่งสอบเทียบ';
    protected static ?string $pluralModelLabel = 'รายการส่งสอบเทียบ';
    protected static ?string $navigationGroup = 'สอบเทียบภายนอก (External)';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['instrument.toolType', 'instrument.department']);
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Section 1: ข้อมูลเครื่องมือ
                Section::make('ข้อมูลเครื่องมือ')
                    ->description('เลือกเครื่องมือที่ต้องการส่งสอบเทียบภายนอก')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            Select::make('instrument_id')
                                ->label('Code No')
                                ->relationship(
                                    'instrument',
                                    'code_no',
                                    fn (Builder $query) => $query
                                        ->where(function (Builder $q) {
                                            $q->where('cal_place', 'External')
                                              ->orWhere('cal_place', 'ExternalCal');
                                        })
                                )
                                ->getOptionLabelFromRecordUsing(fn ($record) => $record->code_no)
                                ->searchable(['code_no'])
                                ->preload()
                                ->required()
                                ->live()
                                ->columnSpan(2)
                                ->afterStateUpdated(function (Set $set, ?string $state) {
                                    if ($state) {
                                        $instrument = Instrument::with(['toolType', 'department'])->find($state);
                                        if ($instrument) {
                                            $set('instrument_name', $instrument->toolType?->name ?? '-');
                                            $set('instrument_size', $instrument->toolType?->size ?? '-');
                                            $set('instrument_serial', $instrument->serial_no ?? '-');
                                            $set('instrument_department', $instrument->department?->name ?? '-');
                                        }
                                    } else {
                                        $set('instrument_name', null);
                                        $set('instrument_size', null);
                                        $set('instrument_serial', null);
                                        $set('instrument_department', null);
                                    }
                                }),

                            TextInput::make('instrument_name')
                                ->label('Name')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),

                            TextInput::make('instrument_size')
                                ->label('Size')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                        ]),

                        Grid::make(6)->schema([
                            TextInput::make('instrument_serial')
                                ->label('Serial No')
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),

                            Select::make('requester')
                                ->label('แผนก')
                                ->options(fn () => \App\Models\Department::pluck('name', 'name')->toArray())
                                ->searchable()
                                ->columnSpan(2),

                            TextInput::make('quantity')
                                ->label('จำนวน')
                                ->numeric()
                                ->default(1)
                                ->columnSpan(2),
                        ]),
                    ]),

                // Section 2: ข้อมูลการจัดซื้อ
                Section::make('ข้อมูลการจัดซื้อ')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            DatePicker::make('pr_date')
                                ->label('วันที่ออก PR')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->columnSpan(2),

                            TextInput::make('pr_no')
                                ->label('PR No')
                                ->columnSpan(2),

                            TextInput::make('po_no')
                                ->label('PO No')
                                ->columnSpan(2),
                        ]),

                        Grid::make(6)->schema([
                            TextInput::make('vendor_name')
                                ->label('สถาบันที่เสนอ')
                                ->placeholder('บริษัทที่เสนอราคา')
                                ->columnSpan(3),

                            TextInput::make('estimated_price')
                                ->label('ราคาที่เสนอ')
                                ->numeric()
                                ->prefix('฿')
                                ->columnSpan(3),
                        ]),

                        Grid::make(6)->schema([
                            TextInput::make('cal_place')
                                ->label('สถานที่สอบเทียบ')
                                ->placeholder('บริษัทที่ส่งไปจริง')
                                ->columnSpan(3),

                            TextInput::make('net_price')
                                ->label('Price (ราคาจริง)')
                                ->numeric()
                                ->prefix('฿')
                                ->columnSpan(3),
                        ]),
                    ]),

                // Section 3: สถานะ
                Section::make('สถานะและวันที่')
                    ->collapsible()
                    ->schema([
                        Grid::make(6)->schema([
                            Select::make('status')
                                ->label('Status')
                                ->options([
                                    'Draft' => 'Draft (ร่าง)',
                                    'Pending' => 'Pending (รอดำเนินการ)',
                                    'Sent' => 'Sent (ส่งแล้ว)',
                                    'Received' => 'Received (รับของแล้ว)',
                                    'Completed' => 'Completed (เสร็จสิ้น)',
                                ])
                                ->default('Draft')
                                ->native(false)
                                ->columnSpan(2),

                            DatePicker::make('send_date')
                                ->label('วันที่ส่ง')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->columnSpan(2),

                            DatePicker::make('receive_date')
                                ->label('Receive Date')
                                ->displayFormat('d/m/Y')
                                ->native(false)
                                ->columnSpan(2),
                        ]),

                        Textarea::make('remark')
                            ->label('Remark')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                // Section 4: Certificate
                Section::make('Certificate')
                    ->collapsible()
                    ->schema([
                        FileUpload::make('certificate_file')
                            ->label('อัพโหลด Certificate PDF')
                            ->disk('public')
                            ->directory('external-certificates')
                            ->acceptedFileTypes(['application/pdf'])
                            ->maxSize(10240) // 10MB
                            ->downloadable()
                            ->openable()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('pr_no')
                    ->label('PR No')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('instrument.code_no')
                    ->label('Code No')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('instrument.toolType.name')
                    ->label('Name')
                    ->limit(25)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('vendor_name')
                    ->label('สถานที่สอบเทียบ')
                    ->limit(20),

                Tables\Columns\TextColumn::make('send_date')
                    ->label('วันที่ส่ง')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'gray' => 'Draft',
                        'warning' => 'Pending',
                        'info' => 'Sent',
                        'success' => 'Received',
                        'primary' => 'Completed',
                    ]),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label('วันที่รับ')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_price')
                    ->label('Price')
                    ->money('THB')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options([
                        'Draft' => 'Draft',
                        'Pending' => 'Pending',
                        'Sent' => 'Sent',
                        'Received' => 'Received',
                        'Completed' => 'Completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->color('gray'),
                Tables\Actions\EditAction::make()->color('warning'),
                Tables\Actions\Action::make('record_result')
                    ->label('บันทึกผล')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->url(fn (PurchasingRecord $record): string => 
                        ExternalCalResultResource::getUrl('create', [
                            'purchasing_id' => $record->id,
                            'instrument_id' => $record->instrument_id,
                        ])
                    )
                    ->visible(fn (PurchasingRecord $record): bool => 
                        in_array($record->status, ['Received', 'Sent'])
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListExternalPurchasings::route('/'),
            'create' => Pages\CreateExternalPurchasing::route('/create'),
            'view' => Pages\ViewExternalPurchasing::route('/{record}'),
            'edit' => Pages\EditExternalPurchasing::route('/{record}/edit'),
        ];
    }
}
