<?php

namespace App\Http\Resources\Management;

use App\Enums\UserRole;
use App\Models\Franchise;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Franchise */
class FranchiseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            'link'        => $this->link,
            'can_edit'    => $request->user() && ($request->user()->role == UserRole::Admin || $this->author_id == $request->user()->id),
            'films'       => FilmResource::collection($this->whenLoaded('films')),
            'films_count' => $this->whenCounted('films')
        ];
    }
}
