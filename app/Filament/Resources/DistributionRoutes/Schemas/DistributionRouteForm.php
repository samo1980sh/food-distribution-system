<?php

namespace App\Filament\Resources\DistributionRoutes\Schemas;

use App\Enums\UserRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class DistributionRouteForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('هوية خط التوزيع')
                    ->description('الرمز والاسم والمنطقة والحالة المستخدمة في العملاء والعمليات اليومية.')
                    ->icon('heroicon-o-map')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('code')
                            ->label('رمز الخط')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('اسم خط التوزيع')
                            ->required()
                            ->maxLength(255),
                        Select::make('area_id')
                            ->label('المنطقة')
                            ->relationship(
                                'area',
                                'name_ar',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->native(false),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('التعطيل يمنع استخدام الخط في العمليات الجديدة مع إبقاء تاريخه.'),
                    ]),

                Section::make('فريق الخط والسيارة')
                    ->description('يتحقق النظام من نشاط الموظف ودوره ومن تطابق السيارة مع السياق التشغيلي.')
                    ->icon('heroicon-o-truck')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('السيارة')
                            ->relationship(
                                'vehicle',
                                'plate_number',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('status', 'active'),
                            )
                            ->searchable()
                            ->preload()
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
                        Select::make('visit_days')
                            ->label('أيام الزيارة')
                            ->multiple()
                            ->options([
                                'saturday' => 'السبت',
                                'sunday' => 'الأحد',
                                'monday' => 'الإثنين',
                                'tuesday' => 'الثلاثاء',
                                'wednesday' => 'الأربعاء',
                                'thursday' => 'الخميس',
                                'friday' => 'الجمعة',
                            ])
                            ->native(false),
                    ]),

                Section::make('ملاحظات التشغيل')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(4)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
