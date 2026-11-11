<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ProjectClient;
use App\Models\WorkType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Get active work types for the user's organisation.
     */
    public function workTypes(Request $request): JsonResponse
    {
        $types = WorkType::where('organisation_id', $request->user()->organisation_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        return response()->json($types);
    }

    /**
     * Get active project/clients for the user's organisation.
     */
    public function projectClients(Request $request): JsonResponse
    {
        $clients = ProjectClient::where('organisation_id', $request->user()->organisation_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'description']);

        return response()->json($clients);
    }
}
