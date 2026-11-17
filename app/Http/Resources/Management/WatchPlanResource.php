<?php

namespace App\Http\Resources\Management;

use App\Models\WatchPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin WatchPlan */
class WatchPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'position' => $this->position,
            'note' => $this->note,
            'started_at' => $this->started_at?->toDateString(),
            'finished_at' => $this->finished_at?->toDateString(),
            'created_by' => $this->created_by,
            'film' => $this->whenLoaded('film', fn () => [
                'id' => $this->film->id,
                'name' => $this->film->name,
                'original_name' => $this->film->original_name,
                'cover' => $this->film->cover,
                'cover_thumb' => $this->film->cover_thumb,
                'format' => $this->film->format,
                'produced_year' => $this->film->produced_year,
                'seasons' => $this->film->seasons,
            ]),
            'participants' => UserCardResource::collection($this->whenLoaded('participants')),
        ];
    }
}
