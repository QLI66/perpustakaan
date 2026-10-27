<?php

namespace App\Http\Controllers;

use App\Models\ClassLevel;
use App\Models\Major;
use App\Models\MemberType;
use App\Traits\ApiResponder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MemberReferenceController extends Controller
{
    use ApiResponder;

    private function authorizeView(): void
    {
        abort_unless(
            Auth::user()->can('members.view'),
            403
        );
    }

    public function memberTypes(): JsonResponse
    {
        $this->authorizeView();

        $items = MemberType::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        return $this->successResponse(
            $items,
            'Member types retrieved successfully.'
        );
    }

    public function classLevels(): JsonResponse
    {
        $this->authorizeView();

        $items = ClassLevel::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        return $this->successResponse(
            $items,
            'Class levels retrieved successfully.'
        );
    }

    public function majors(): JsonResponse
    {
        $this->authorizeView();

        $items = Major::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
            ]);

        return $this->successResponse(
            $items,
            'Majors retrieved successfully.'
        );
    }
}
