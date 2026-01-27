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
    protected static ?string $modelLabel = 'In External';
    protected static ?string $pluralModelLabel = 'In External';
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
                                ->formatStateUsing(fn ($state, ?PurchasingRecord $record) => $state ?? $record?->instrument?->toolType?->name)
                                ->columnSpan(2),

                            TextInput::make('instrument_size')
                                ->label('Size')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn ($state, ?PurchasingRecord $record) => $state ?? $record?->instrument?->toolType?->size)
                                ->columnSpan(2),
                        ]),

                        Grid::make(6)->schema([
                            TextInput::make('instrument_serial')
                                ->label('Serial No')
                                ->disabled()
                                ->dehydrated(false)
                                ->formatStateUsing(fn ($state, ?PurchasingRecord $record) => $state ?? $record?->instrument?->serial_no)
                                ->columnSpan(2),

                            TextInput::make('instrument_department')
                                ->label('แผนก')
                                ->disabled()    
                                ->dehydrated(false)
                                ->formatStateUsing(function ($state, ?PurchasingRecord $record) {
                                    if ($record) {
                                        return $record->instrument?->department?->name ?? '-';
                                    }
                                    return $state;
                                })
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
                            TextInput::make('requester')
                                ->label('สถานที่สอบเทียบเสนอ')
                                ->columnSpan(3),

                            TextInput::make('estimated_price')
                                ->label('ราคาที่เสนอ (บาท)')
                                ->numeric()
                                ->prefix('฿')
                                ->columnSpan(3),
                        ]),

                        Grid::make(6)->schema([

                            TextInput::make('vendor_name')
                                ->label('สถานที่สอบเทียบจริง')
                                ->columnSpan(3),

                            TextInput::make('net_price')
                                ->label('ราคาจริง (บาท)')
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
                                ->live()
                                ->afterStateUpdated(function (Set $set, $state) {
                                    if ($state === 'Received') {
                                        $set('receive_date', now()->format('Y-m-d'));
                                    } elseif ($state === 'Sent') {
                                        $set('send_date', now()->format('Y-m-d'));
                                    }
                                })
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
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('instrument.code_no')
                    ->label('Code No')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('instrument.toolType.name')
                    ->label('Name')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->limit(25)
                    ->tooltip(fn ($state) => $state),

                Tables\Columns\TextColumn::make('requester')
                    ->label('สถานที่สอบเทียบเสนอ')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn ($state) => $state)
                    ->limit(20),

                Tables\Columns\TextColumn::make('vendor_name')
                    ->label('สถานที่สอบเทียบจริง')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->tooltip(fn ($state) => $state)
                    ->limit(20),

                Tables\Columns\TextColumn::make('send_date')
                    ->label('วันที่ส่ง')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\BadgeColumn::make('status')
                    ->label('Status')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->colors([
                        'gray' => 'Draft',
                        'warning' => 'Pending',
                        'info' => 'Sent',
                        'success' => 'Received',
                        'primary' => 'Completed',
                    ]),

                Tables\Columns\TextColumn::make('receive_date')
                    ->label('วันที่รับ')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('estimated_price')
                    ->label('ราคาเสนอ')
                    ->toggleable(isToggledHiddenByDefault: false)
                    ->money('THB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('net_price')
                    ->label('ราคาจริง')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->money('THB')
                    ->sortable(),

                Tables\Columns\TextColumn::make('remark')
                    ->label('Remark')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(30)
                    ->tooltip(fn($state) => $state),
            ])
            ->filters([
                Tables\Filters\Filter::make('pr_date')
                    ->label('วันที่ออก PR')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('ตั้งแต่วันที่'),
                        Forms\Components\DatePicker::make('until')->label('ถึงวันที่'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('pr_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('pr_date', '<=', $date),
                            );
                    }),
                Tables\Filters\SelectFilter::make('vendor_name')
                    ->label('Vendor (บริษัท)')
                    ->options(fn () => PurchasingRecord::query()->whereNotNull('vendor_name')->distinct()->pluck('vendor_name', 'vendor_name')->toArray())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('instrument_id')
                    ->label('Instrument (เครื่องมือ)')
                    ->relationship('instrument', 'code_no', fn (Builder $query) => $query->where(function ($q) {
                        $q->where('cal_place', 'External')->orWhere('cal_place', 'ExternalCal');
                    }))
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('status')
                    ->label('สถานะ')
                    ->options([
                        'Draft' => 'Draft',
                        'Pending' => 'Pending',
                        'Sent' => 'Sent',
                        'Received' => 'Received',
                        'Completed' => 'Completed',
                    ]),
            ], layout: Tables\Enums\FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(3)
            ->actions([
                Tables\Actions\ViewAction::make()->color('gray'),
                Tables\Actions\EditAction::make()->color('warning'),
                Tables\Actions\Action::make('update_status')
                    ->label('Set Status')
                    ->icon('heroicon-m-wrench')
                    ->color('info')
                    ->form([
                        Select::make('status')
                            ->label('Status')
                            ->options([
                                'Draft' => 'Draft (ร่าง)',
                                'Pending' => 'Pending (รอดำเนินการ)',
                                'Sent' => 'Sent (ส่งแล้ว)',
                                'Received' => 'Received (รับของแล้ว)',
                                'Completed' => 'Completed (เสร็จสิ้น)',
                            ])
                            ->required()
                            ->native(false)
                            ->default(fn (PurchasingRecord $record) => $record->status),
                        Textarea::make('remark')
                            ->label('หมายเหตุ (Remark)')
                            ->rows(3),
                    ])
                    ->action(function (PurchasingRecord $record, array $data) {
                        $oldStatus = $record->status;
                        $newStatus = $data['status'];
                        $remark = $data['remark'] ?? null;

                        // Save History
                        \App\Models\PurchasingStatusHistory::create([
                            'purchasing_record_id' => $record->id,
                            'old_status' => $oldStatus,
                            'new_status' => $newStatus,
                            'remark' => $remark,
                            'changed_by' => auth()->id(),
                        ]);

                        $updateData = [
                            'status' => $newStatus,
                            'remark' => $remark ?? $record->remark,
                        ];

                        if ($newStatus === 'Sent' && empty($record->send_date)) {
                            $updateData['send_date'] = now();
                        } elseif ($newStatus === 'Received' && empty($record->receive_date)) {
                            $updateData['receive_date'] = now();
                        }

                        $record->update($updateData);
                    })
                    ->requiresConfirmation()
                    ->modalHeading('ยืนยันการเปลี่ยนสถานะ')
                    ->modalDescription('คุณต้องการเปลี่ยนสถานะรายการนี้ใช่หรือไม่?')
                    ->modalSubmitActionLabel('ยืนยัน (Confirm)'),
                
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
        return [
            ExternalPurchasingResource\RelationManagers\StatusHistoriesRelationManager::class,
        ];
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
