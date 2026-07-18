<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Filament\Resources\Users\Actions\UserActions;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Services\Authorization\AccessScopeService;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('المستخدم')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (User $record): string => $record->email),

                TextColumn::make('roles.name')
                    ->label('الأدوار')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => UserRole::tryFrom((string) $state)?->label()
                            ?? $state
                            ?? '-',
                    )
                    ->color(
                        fn (?string $state): string => UserRole::tryFrom((string) $state)?->color()
                            ?? 'gray',
                    ),

                TextColumn::make('employee.name')
                    ->label('الموظف المرتبط')
                    ->searchable()
                    ->placeholder('غير مرتبط')
                    ->description(fn (User $record): ?string => $record->employee?->employee_code),

                TextColumn::make('scope_summary')
                    ->label('نطاق الوصول')
                    ->state(fn (User $record): string => self::scopeSummary($record))
                    ->badge()
                    ->color(fn (User $record): string => app(AccessScopeService::class)->for($record)->hasAssignments() ? 'success' : 'danger'),

                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'فعّال',
                        User::STATUS_INACTIVE => 'غير فعّال',
                        default => $state ?? '-',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        User::STATUS_ACTIVE => 'success',
                        User::STATUS_INACTIVE => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('تاريخ الإنشاء')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('آخر تحديث')
                    ->dateTime('Y-m-d H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('الدور')
                    ->options(
                        fn (): array => UserRole::options(
                            includeSuperAdmin: auth()->user()?->isSuperAdmin() === true,
                        ),
                    )
                    ->query(
                        fn (Builder $query, array $data): Builder => $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query->role($data['value']),
                        ),
                    ),

                SelectFilter::make('employee_type')
                    ->label('نوع الموظف المرتبط')
                    ->options([
                        'driver' => 'سائق',
                        'sales_representative' => 'مندوب مبيعات',
                        'warehouse_keeper' => 'أمين مستودع',
                        'accountant' => 'محاسب',
                        'supervisor' => 'مشرف',
                    ])
                    ->query(
                        fn (Builder $query, array $data): Builder => $query->when(
                            filled($data['value'] ?? null),
                            fn (Builder $query): Builder => $query->whereHas(
                                'employee',
                                fn (Builder $employeeQuery): Builder => $employeeQuery->where('type', $data['value']),
                            ),
                        ),
                    ),

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        User::STATUS_ACTIVE => 'فعّال',
                        User::STATUS_INACTIVE => 'غير فعّال',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()->label('عرض الحساب والنطاق'),
                    EditAction::make()
                        ->label('تعديل الحساب')
                        ->visible(fn (User $record): bool => auth()->user()?->can('update', $record) === true),
                    UserActions::activate(),
                    UserActions::deactivate(),
                ])
                    ->label('الإجراءات')
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button(),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc')
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->paginationPageOptions([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateIcon('heroicon-o-users')
            ->emptyStateHeading('لا توجد حسابات مستخدمين')
            ->emptyStateDescription('أنشئ حسابًا جديدًا وحدد دوره ونطاق وصوله، أو غيّر عوامل التصفية الحالية.');
    }

    private static function scopeSummary(User $record): string
    {
        $scope = app(AccessScopeService::class)->for($record);

        if ($scope->unrestricted) {
            return 'وصول شامل';
        }

        if (! $scope->hasAssignments()) {
            return 'نطاق فارغ';
        }

        return sprintf(
            '%d منطقة · %d خط · %d سيارة · %d مستودع',
            count($scope->areaIds),
            count($scope->routeIds),
            count($scope->vehicleIds),
            count($scope->warehouseIds),
        );
    }
}
