<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;

trait BelongsToUserPlant
{
    protected function plantId(): int
    {
        $plantId = auth()->user()?->plant_id;

        abort_unless($plantId, 403, 'No plant assigned to your account.');

        return (int) $plantId;
    }

    protected function authorizePlant(Model $model, string $column = 'plant_id'): void
    {
        if ((int) $model->getAttribute($column) !== $this->plantId()) {
            abort(404);
        }
    }

    protected function purchaserSeesAllPlants(): bool
    {
        return auth()->user()?->isPurchaser() ?? false;
    }

    protected function authorizePlantAccess(Model $model, string $column = 'plant_id'): void
    {
        if ($this->purchaserSeesAllPlants()) {
            return;
        }

        $this->authorizePlant($model, $column);
    }
}
