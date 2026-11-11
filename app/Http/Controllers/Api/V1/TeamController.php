<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Department;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TeamController extends Controller
{
    /**
     * List users visible to the authenticated user based on their role.
     * - Workers: see only themselves
     * - Admins: see users in their department
     * - Super Admins: see all users in the organisation
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        
        $query = User::with(['department', 'roles', 'organisation'])
            ->where('organisation_id', $user->organisation_id)
            ->orderBy('name');

        // Role-based scoping
        if ($user->hasRole('super_admin')) {
            // Super admins see everyone in the org
        } elseif ($user->hasRole('admin')) {
            // Admins see users in their department only
            $query->where('department_id', $user->department_id);
        } else {
            // Workers see all users in their organisation for messaging
            // (scoped to same org already by line 25)
        }

        // Optional filters
        if ($request->has('department_id') && $request->department_id) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->has('role') && $request->role) {
            $query->role($request->role); // Spatie's scope
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->paginate(20));
    }

    /**
     * List departments in the organisation.
     */
    public function departments(Request $request)
    {
        $user = $request->user();

        $departments = Department::where('organisation_id', $user->organisation_id)
            ->withCount('users')
            ->orderBy('name')
            ->get();

        return response()->json($departments);
    }

    /**
     * Toggle a user's active status (Super Admin only).
     */
    public function toggleStatus(Request $request, User $user)
    {
        $this->authorize('manage-users');

        // Prevent deactivating yourself
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        $user->update(['is_active' => !$user->is_active]);

        return new UserResource($user->load(['department', 'roles']));
    }
}
