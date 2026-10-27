<?php

namespace App\Http\Resources;

use App\Support\LoanLateness;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LoanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->whenLoaded('items') ? $this->items : collect();

        $totalItems = $items->count();
        $activeItems = $items->where('status', 'ACTIVE')->count();
        $returnedItems = $items->where('status', 'RETURNED')->count();

        $overdueItems = $items->where('status', 'ACTIVE')->filter(function ($item) {
            return LoanLateness::isOverdue($item->due_at, now(), $item->duration_unit);
        })->count();

        return [
            'id' => $this->id,
            'loan_number' => $this->loan_number,
            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'member_number' => $this->member->member_number,
                'name' => $this->member->name,
                'member_type' => $this->member->relationLoaded('memberType') && $this->member->memberType ? [
                    'id' => $this->member->memberType->id,
                    'code' => $this->member->memberType->code,
                    'name' => $this->member->memberType->name,
                ] : null,
            ]),
            'borrowed_at' => $this->borrowed_at?->toISOString(),
            'status' => $this->status,
            'notes' => $this->notes,
            'items' => LoanItemResource::collection($this->whenLoaded('items')),
            'summary' => [
                'total_items' => $totalItems,
                'active_items' => $activeItems,
                'returned_items' => $returnedItems,
                'overdue_items' => $overdueItems,
            ],
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
