<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog item of type "service" — the Ponudba entries appointments are
 * booked from. Backed by the same catalog_items table as Product; scoped to
 * type=service so both remain distinct in every query and route-model bind.
 */
class Service extends CatalogItem
{
    protected static function booted(): void
    {
        static::addGlobalScope('type', fn ($query) => $query->where('type', 'service'));

        static::creating(fn (Service $service) => $service->type = 'service');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
