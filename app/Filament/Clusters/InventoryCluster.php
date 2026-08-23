<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Pages\Enums\SubNavigationPosition;

class InventoryCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?SubNavigationPosition $subNavigationPosition = SubNavigationPosition::Top;

    protected static ?string $clusterBreadcrumb = 'المخزون';

    public static function getNavigationLabel(): string
    {
        return 'إدارة المخزون';
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'المخزون';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }
}
