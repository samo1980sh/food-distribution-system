<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
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
                Section::make('بيانات الموظف')
                    ->description('الهوية الوظيفية وبيانات الاتصال والحالة المستخدمة في الخطوط والعمليات.')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('employee_code')
                            ->label('رمز الموظف')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        TextInput::make('name')
                            ->label('اسم الموظف')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('الهاتف')
                            ->tel()
                            ->maxLength(255),
                        TextInput::make('job_title')
                            ->label('المسمى الوظيفي')
                            ->maxLength(255),
                        Select::make('status')
                            ->label('الحالة')
                            ->options([
                                'active' => 'فعال',
                                'inactive' => 'غير فعال',
                            ])
                            ->default('active')
                            ->required()
                            ->native(false)
                            ->helperText('الموظف الفعال فقط يمكن تعيينه في السياقات التشغيلية الجديدة.'),
                    ]),

                Section::make('الدور التشغيلي وحساب المستخدم')
                    ->description('نوع الموظف هو تصنيفه الأساسي، ويجب أن يحمل الحساب المرتبط الدور المطابق. يمكن للحساب الميداني الجمع بين السائق والمندوب.')
                    ->icon('heroicon-o-link')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
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
                            ->native(false)
                            ->helperText('اتركه فارغًا للموظف الذي لا يحتاج حساب دخول.'),
                    ]),

                Section::make('ملاحظات داخلية')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Textarea::make('notes')->label('ملاحظات')->rows(4)->columnSpanFull(),
                    ]),
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
