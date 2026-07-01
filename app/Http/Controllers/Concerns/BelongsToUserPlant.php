<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

trait BelongsToUserSite
{
    protected function siteId(): int
    {
        $siteId = auth()->user()?->site_id;

        abort_unless($siteId, 403, 'No plant assigned to your account.');

        return (int) $siteId;
    }

    protected function authorizeSite(Model $model, string $column = 'site_id'): void
    {
        if ((int) $model->getAttribute($column) !== $this->siteId()) {
            abort(404);
        }
    }

    protected function purchaserSeesAllSites(): bool
    {
        return auth()->user()?->isPurchaser() ?? false;
    }

    protected function authorizeSiteAccess(Model $model, string $column = 'site_id'): void
    {
        if ($this->purchaserSeesAllSites()) {
            return;
        }

        $this->authorizeSite($model, $column);
    }
}
