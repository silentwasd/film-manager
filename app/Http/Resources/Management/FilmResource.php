<?php

namespace App\Http\Resources\Management;

use App\Enums\FilmCinemaStatus;
use App\Enums\UserRole;
use App\Models\Film;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Film */
class FilmResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'            => $this->id,
            'author_id'     => $this->author_id,
            'name'          => $this->name,
            'original_name' => $this->original_name,
            'format'        => $this->format,
            'cover'         => $this->cover,
            'produced_year' => $this->produced_year,
            'release_date'  => $this->release_date?->format('Y-m-d'),
            'description'   => $this->description,
            'is_mine'       => $this->watchers()->where('watcher_id', $request->user()?->id)->exists(),
            'can_edit'      => $this->when(
                !!$request->user(),
                fn() => ($request->user()->role == UserRole::Admin) ||
                        (
                            $this->author_id == $request->user()->id &&
                            $this->watchers()->where('watcher_id', '!=', $request->user()?->id)->doesntExist() &&
                            $this->ratings()->where('user_id', '!=', $request->user()?->id)->doesntExist()
                        )
            ),
            'can_watch'     => $this->cinema_status == FilmCinemaStatus::Published,
            'ratings'       => RatingResource::collection($this->whenLoaded('ratings')),
            'people'        => FilmPersonResource::collection($this->whenLoaded('people')),
            'genres'        => GenreResource::collection($this->whenLoaded('genres')),
            'companies'     => CompanyResource::collection($this->whenLoaded('companies')),
            'countries'     => CountryResource::collection($this->whenLoaded('countries')),
            'tags'          => TagResource::collection($this->whenLoaded('tags'))
        ];
    }
}
