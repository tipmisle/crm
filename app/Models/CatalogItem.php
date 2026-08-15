<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * The reusable "Ponudba" catalog shared by Products (used on Orders) and
 * Services (used on Appointments). Product and Service are thin, scoped
 * subclasses of this same table (see their class docblocks) rather than
 * two duplicated CRUD/UI systems.
 */
class CatalogItem extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'catalog_items';

    protected $fillable = [
        'workspace_id',
        'type',
        'name',
        'description',
        'default_duration_minutes',
        'default_price',
        'default_deposit_amount',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'default_deposit_amount' => 'decimal:2',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
