<?php

namespace App\Filament\Resources\VehicleExpenses\Schemas;

use App\Enums\UserRole;
use App\Models\VehicleExpense;
use App\Support\Filament\OperationalFormContext;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class VehicleExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('بيانات المصروف')
                    ->description('أدخل تاريخ المصروف ونوعه وقيمته وطريقة الدفع.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        DatePicker::make('expense_date')
                            ->label('تاريخ المصروف')
                            ->default(now())
                            ->required()
                            ->native(false),

                        Select::make('expense_type')
                            ->label('نوع المصروف')
                            ->options([
                                'fuel' => 'وقود',
                                'maintenance' => 'صيانة',
                                'washing' => 'غسيل',
                                'fees' => 'رسوم',
                                'parking' => 'موقف',
                                'emergency' => 'طارئ',
                                'other' => 'أخرى',
                            ])
                            ->required()
                            ->native(false),

                        TextInput::make('amount')
                            ->label('المبلغ')
                            ->numeric()
                            ->minValue(0.01)
                            ->step('0.01')
                            ->default(0)
                            ->required(),

                        Select::make('payment_method')
                            ->label('طريقة الدفع')
                            ->options([
                                'cash' => 'نقدي',
                                'bank_transfer' => 'تحويل بنكي',
                                'cheque' => 'شيك',
                                'other' => 'أخرى',
                            ])
                            ->default('cash')
                            ->required()
                            ->native(false),
                    ]),

                Section::make('السياق التشغيلي')
                    ->description('اربط المصروف بالسيارة ومستودعها وخط التوزيع حتى يدخل في الإغلاق الصحيح.')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('السيارة')
                            ->relationship('vehicle', 'plate_number', modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $set('route_id', null);
                                $set('driver_id', null);
                                $set('sales_representative_id', null);
                                $set('warehouse_id', OperationalFormContext::vehicleWarehouseId($state));
                            })
                            ->native(false),

                        Select::make('warehouse_id')
                            ->label('مستودع السيارة')
                            ->relationship(
                                'warehouse',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('status', 'active')
                                    ->where('type', 'vehicle')
                                    ->when($get('vehicle_id'), fn (Builder $query, $vehicleId): Builder => $query->where('vehicle_id', $vehicleId)),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false)
                            ->helperText('يتم ربط المصروف بمستودع السيارة حتى يدخل في الإغلاق الصحيح.'),

                        Select::make('route_id')
                            ->label('خط التوزيع')
                            ->relationship(
                                'route',
                                'name',
                                modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                                    ->where('status', 'active')
                                    ->when($get('vehicle_id'), fn (Builder $query, $vehicleId): Builder => $query->where('vehicle_id', $vehicleId)),
                            )
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (mixed $state, Set $set): void {
                                $context = OperationalFormContext::forRoute($state);

                                if (filled($context['vehicle_id'])) {
                                    $set('vehicle_id', $context['vehicle_id']);
                                }

                                if (filled($context['warehouse_id'])) {
                                    $set('warehouse_id', $context['warehouse_id']);
                                }

                                $set('driver_id', $context['driver_id']);
                                $set('sales_representative_id', $context['sales_representative_id']);
                            })
                            ->native(false),

                        Select::make('driver_id')
                            ->label('السائق')
                            ->relationship(
                                'driver',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('status', 'active')
                                    ->forOperationalRole(UserRole::DRIVER),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false),

                        Select::make('sales_representative_id')
                            ->label('مندوب المبيعات')
                            ->relationship(
                                'salesRepresentative',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query
                                    ->where('status', 'active')
                                    ->forOperationalRole(UserRole::SALES_REPRESENTATIVE),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ]),

                Section::make('الإيصال والملاحظات')
                    ->icon('heroicon-o-document-text')
                    ->columns(1)
                    ->columnSpanFull()
                    ->schema([
                        FileUpload::make('receipt_path')
                            ->label('صورة الإيصال')
                            ->disk('public')
                            ->directory('vehicle-expense-receipts')
                            ->image()
                            ->imagePreviewHeight('180')
                            ->openable()
                            ->downloadable(),

                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(4),

                        Textarea::make('rejection_reason')
                            ->label('سبب الرفض')
                            ->disabled()
                            ->dehydrated()
                            ->hidden(fn (?VehicleExpense $record): bool => ! $record?->isRejected())
                            ->rows(4),
                    ]),
            ]);
    }
}
