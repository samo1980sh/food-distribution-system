<?php

namespace App\Filament\Resources\Users\Tables;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Authorization\UserScopeAssignmentService;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('البريد الإلكتروني')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('roles.name')
                    ->label('الدور')
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

                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        User::STATUS_ACTIVE => 'فعّال',
                        User::STATUS_INACTIVE => 'غير فعّال',
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(
                        fn (User $record): bool => auth()->user()?->can('update', $record) === true,
                    )
                    ->label('تعديل')
                    ->modalHeading('تعديل مستخدم')
                    ->slideOver()
                    ->after(fn (User $record) => app(UserScopeAssignmentService::class)->normalize($record)),
            ])
            ->toolbarActions([])
            ->defaultSort('created_at', 'desc');
    }
}
