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
            $table = $builder->getModel()->getTable();

            // Critical: an authenticated request with no resolvable
            // workspace (e.g. a platform admin, whose current_workspace_id
            // is null) must NEVER fall through to an unscoped query — that
            // would leak every workspace's rows. Default to a condition
            // that matches nothing rather than omitting the scope.
            if (Auth::check() && Auth::user()->current_workspace_id) {
                $builder->where($table.'.workspace_id', Auth::user()->current_workspace_id);
            } elseif (Auth::check()) {
                $builder->whereRaw('1 = 0');
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
