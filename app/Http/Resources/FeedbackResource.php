<?php

namespace App\Http\Resources;

use App\Http\Resources\Management\UserResource;
use App\Models\Feedback;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Feedback */
class FeedbackResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'user'       => new UserResource($this->whenLoaded('user')),
            'season'     => $this->season,
            'text'       => $this->text,
            'reaction'   => $this->reaction,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}
