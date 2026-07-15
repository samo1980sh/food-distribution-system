<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('employee_code')->label('رمز الموظف')->required()->unique(ignoreRecord: true)->maxLength(255),
                TextInput::make('name')->label('اسم الموظف')->required()->maxLength(255),
                TextInput::make('phone')->label('الهاتف')->tel()->maxLength(255),
                TextInput::make('job_title')->label('المسمى الوظيفي')->maxLength(255),

                Select::make('type')
                    ->label('نوع الموظف')
                    ->options([
                        'driver' => 'سائق',
                        'sales_representative' => 'مندوب مبيعات',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                        'supervisor' => 'مشرف',
                    ])
                    ->default('driver')
                    ->required()
                    ->live()
                    ->native(false),

                Select::make('user_id')
                    ->label('حساب المستخدم')
                    ->relationship(
                        'user',
                        'name',
                        modifyQueryUsing: fn (Builder $query, Get $get): Builder => $query
                            ->when(
                                self::roleForEmployeeType($get('type')),
                                fn (Builder $query, string $role): Builder => $query->role($role),
                                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
                            ),
                    )
                    ->searchable()
                    ->preload()
                    ->native(false),

                Select::make('status')
                    ->label('الحالة')
                    ->options([
                        'active' => 'فعال',
                        'inactive' => 'غير فعال',
                    ])
                    ->default('active')
                    ->required()
                    ->native(false),

                Textarea::make('notes')->label('ملاحظات')->columnSpanFull(),
            ]);
    }

    private static function roleForEmployeeType(?string $type): ?string
    {
        return match ($type) {
            'driver' => 'driver',
            'sales_representative' => 'sales_representative',
            'warehouse_keeper' => 'warehouse_keeper',
            'accountant' => 'accountant',
            'supervisor' => 'supervisor',
            default => null,
        };
    }
}
