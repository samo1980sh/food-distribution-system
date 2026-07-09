<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\ManageUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'التهيئة الأساسية';
    }

    public static function getNavigationLabel(): string
    {
        return 'المستخدمون';
    }

    public static function getModelLabel(): string
    {
        return 'مستخدم';
    }

    public static function getPluralModelLabel(): string
    {
        return 'المستخدمون';
    }

    public static function getNavigationSort(): ?int
    {
        return 5;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->canManageUsers() === true;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageUsers() === true;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->canManageUsers() === true;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->canManageUsers() === true;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageUsers::route('/'),
        ];
    }
}