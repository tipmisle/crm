<?php

namespace App\Models\Concerns;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToWorkspace
{
    public static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope(function (Builder $builder) {
            if (Auth::check() && Auth::user()->current_workspace_id) {
                $builder->where(
                    $builder->getModel()->getTable().'.workspace_id',
                    Auth::user()->current_workspace_id
                );
            }
        });

        static::creating(function ($model) {
            if (! $model->workspace_id && Auth::check()) {
                $model->workspace_id = Auth::user()->current_workspace_id;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
