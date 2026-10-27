<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'member_number' => $this->member_number,
            'name' => $this->name,
            'identity_number' => $this->identity_number,
            'member_type' => $this->whenLoaded('memberType', fn () => [
                'id' => $this->memberType->id,
                'code' => $this->memberType->code,
                'name' => $this->memberType->name,
            ]),
            'class_level' => $this->whenLoaded('classLevel', fn () => $this->classLevel ? [
                'id' => $this->classLevel->id,
                'code' => $this->classLevel->code,
                'name' => $this->classLevel->name,
            ] : null),
            'major' => $this->whenLoaded('major', fn () => $this->major ? [
                'id' => $this->major->id,
                'code' => $this->major->code,
                'name' => $this->major->name,
            ] : null),
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                // password, remember_token, tokens intentionally omitted
            ] : null),
            'joined_at' => $this->joined_at?->toDateString(),
            'status' => $this->status,
            'notes' => $this->notes,
        ];
    }
}
