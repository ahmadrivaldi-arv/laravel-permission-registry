<?php

declare(strict_types=1);

namespace Ahmdrv\PermissionRegistry\Tests\Fixtures\Resources;

use Ahmdrv\PermissionRegistry\Enums\RiskLevel;
use Ahmdrv\PermissionRegistry\Resources\PermissionResource;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionAction;
use Ahmdrv\PermissionRegistry\ValueObjects\PermissionRecommendation;

final class ProductPermissionResource extends PermissionResource
{
    public static function key(): string
    {
        return 'products';
    }

    public static function label(): string
    {
        return 'Products';
    }

    public static function group(): string
    {
        return 'catalog';
    }

    protected static function actions(): array
    {
        return [
            PermissionAction::make('publish')
                ->label('Publish product')
                ->risk(RiskLevel::HIGH)
                ->directGrantable()
                ->recommend(PermissionRecommendation::make('reports.view_any')->reason('Useful for report history.')),
            PermissionAction::make('delete')->risk(RiskLevel::CRITICAL),
        ];
    }
}
