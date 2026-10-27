<?php

namespace App\Http\Controllers;

use App\Http\Requests\MemberIndexRequest;
use App\Http\Requests\StoreMemberRequest;
use App\Http\Requests\UpdateMemberRequest;
use App\Http\Resources\MemberResource;
use App\Models\Member;
use App\Traits\ApiResponder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class MemberController extends Controller
{
    use ApiResponder;

    private function baseQuery(): Builder
    {
        return Member::query()->with(['memberType', 'classLevel', 'major', 'user']);
    }

    // ─── GET /api/members ───────────────────────────────────────────────────────

    public function index(MemberIndexRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('members.view'), 403);

        $query = $this->baseQuery();

        // Search
        if ($search = $request->input('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('member_number', 'ilike', '%'.$search.'%')
                    ->orWhere('name', 'ilike', '%'.$search.'%')
                    ->orWhere('identity_number', 'ilike', '%'.$search.'%');
            });
        }

        // Filters
        if ($memberTypeId = $request->input('member_type_id')) {
            $query->where('member_type_id', $memberTypeId);
        }
        if ($classLevelId = $request->input('class_level_id')) {
            $query->where('class_level_id', $classLevelId);
        }
        if ($majorId = $request->input('major_id')) {
            $query->where('major_id', $majorId);
        }
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        // Sorting
        $sort = $request->input('sort', 'name');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);

        // Pagination
        $perPage = min((int) $request->input('per_page', 15), 50);
        $paginator = $query->paginate($perPage);

        return $this->successResponse([
            'items' => MemberResource::collection($paginator->items()),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ], 'Members retrieved successfully.');
    }

    // ─── GET /api/members/{member} ──────────────────────────────────────────────

    public function show(Member $member): JsonResponse
    {
        $user = Auth::user();

        // MEMBER can only see their own profile
        if ($user->hasRole('MEMBER') && ! $user->hasPermissionTo('members.view')) {
            if ($user->member?->id !== $member->id) {
                abort(403);
            }
        } elseif (! $user->hasPermissionTo('members.view')) {
            // No members.view and not their own
            abort(403);
        }

        $member->load(['memberType', 'classLevel', 'major', 'user']);

        return $this->successResponse(new MemberResource($member), 'Member retrieved successfully.');
    }

    // ─── GET /api/me/member ─────────────────────────────────────────────────────

    public function myMember(): JsonResponse
    {
        $user = Auth::user();
        $member = $user->member;

        if (! $member) {
            abort(404);
        }

        $member->load(['memberType', 'classLevel', 'major', 'user']);

        return $this->successResponse(new MemberResource($member), 'Member profile retrieved successfully.');
    }

    // ─── POST /api/members ──────────────────────────────────────────────────────

    public function store(StoreMemberRequest $request): JsonResponse
    {
        abort_unless(Auth::user()->can('members.create'), 403);

        $member = Member::create($request->validated());
        $member->load(['memberType', 'classLevel', 'major', 'user']);

        return $this->successResponse(new MemberResource($member), 'Member created successfully.', 201);
    }

    // ─── PATCH /api/members/{member} ────────────────────────────────────────────

    public function update(UpdateMemberRequest $request, Member $member): JsonResponse
    {
        abort_unless(Auth::user()->can('members.update'), 403);

        $member->update($request->validated());
        $member->refresh()->load(['memberType', 'classLevel', 'major', 'user']);

        return $this->successResponse(new MemberResource($member), 'Member updated successfully.');
    }

    // ─── DELETE /api/members/{member} ───────────────────────────────────────────
    //
    // DECISION: Members are soft-blocked from deletion when they have active or
    // historical loans/fines to preserve referential integrity of transaction
    // history. If no loan records exist, hard deletion is permitted.
    // We do NOT add a soft-delete migration in this task.

    public function destroy(Member $member): JsonResponse
    {
        abort_unless(Auth::user()->can('members.delete'), 403);

        // Block deletion if member has any loan history (active or historical)
        if ($member->loans()->exists()) {
            return $this->errorResponse(
                'Member cannot be deleted because they have loan history. Consider deactivating the member instead.',
                409
            );
        }

        $member->delete();

        return $this->successResponse(null, 'Member deleted successfully.');
    }
}
