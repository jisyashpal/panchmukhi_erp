<?php

namespace App\Services;

use App\Models\Plant;
use App\Models\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlantDocumentNumberService
{
    /**
     * Next plant-wise document number, e.g. piska-01, piska-02.
     */
    public function nextReferenceNo(int $plantId): string
    {
        return DB::transaction(function () use ($plantId) {
            $plant = Plant::query()->lockForUpdate()->findOrFail($plantId);
            $prefix = $this->prefixFor($plant);

            $existing = Purchase::query()
                ->where('plant_id', $plantId)
                ->where('reference_no', 'like', $prefix.'-%')
                ->lockForUpdate()
                ->pluck('reference_no');

            $max = 0;
            $pattern = '/^'.preg_quote($prefix, '/').'-(\d+)$/i';

            foreach ($existing as $ref) {
                if (preg_match($pattern, (string) $ref, $matches)) {
                    $max = max($max, (int) $matches[1]);
                }
            }

            $next = $max + 1;
            $pad = $next < 100 ? 2 : (int) ceil(log10($next + 1));

            return $prefix.'-'.str_pad((string) $next, max(2, $pad), '0', STR_PAD_LEFT);
        });
    }

    public function peekNextReferenceNo(int $plantId): string
    {
        $plant = Plant::findOrFail($plantId);
        $prefix = $this->prefixFor($plant);

        $existing = Purchase::query()
            ->where('plant_id', $plantId)
            ->where('reference_no', 'like', $prefix.'-%')
            ->pluck('reference_no');

        $max = 0;
        $pattern = '/^'.preg_quote($prefix, '/').'-(\d+)$/i';

        foreach ($existing as $ref) {
            if (preg_match($pattern, (string) $ref, $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        $next = $max + 1;

        return $prefix.'-'.str_pad((string) $next, 2, '0', STR_PAD_LEFT);
    }

    public function prefixFor(Plant $plant): string
    {
        $prefix = Str::lower(Str::slug((string) $plant->name));

        return $prefix !== '' ? $prefix : 'plant';
    }
}
