<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\Admin\Campaign\CreateCampaignAction;
use App\Actions\Admin\Campaign\UpdateCampaignAction;
use App\Enums\CampaignEvent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Campaign\StoreCampaignRequest;
use App\Http\Requests\Admin\Campaign\UpdateCampaignRequest;
use App\Http\Resources\CampaignListResource;
use App\Http\Resources\CampaignResource;
use App\Models\Campaign;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 20);
        $perPage = max(1, min(100, $perPage));

        $query = Campaign::query()->with('emailTemplate:id,name');

        if ($request->filled('search')) {
            $search = (string) $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        if ($request->filled('send_type')) {
            $query->where('send_type', $request->input('send_type'));
        }

        if ($request->filled('event')) {
            $query->where('event', $request->input('event'));
        }

        if ($request->has('is_active') && $request->input('is_active') !== null) {
            $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isActive !== null) {
                $query->where('is_active', $isActive);
            }
        }

        $paginator = $query->orderByDesc('created_at')->paginate($perPage);

        return response()->json([
            'status' => true,
            'message' => 'Campaigns retrieved successfully.',
            'data' => [
                'current_page' => $paginator->currentPage(),
                'data' => CampaignListResource::collection($paginator->items()),
                'first_page_url' => $paginator->url(1),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'last_page_url' => $paginator->url($paginator->lastPage()),
                'links' => $paginator->linkCollection()->toArray(),
                'next_page_url' => $paginator->nextPageUrl(),
                'path' => $paginator->path(),
                'per_page' => $paginator->perPage(),
                'prev_page_url' => $paginator->previousPageUrl(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ], 200);
    }

    public function store(StoreCampaignRequest $request, CreateCampaignAction $action): JsonResponse
    {
        $campaign = $action->execute($request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Campaign created successfully.',
            'data' => new CampaignResource($campaign->load('emailTemplate')),
        ], 201);
    }

    public function show(Campaign $campaign): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Campaign retrieved successfully.',
            'data' => new CampaignResource($campaign->load('emailTemplate')),
        ], 200);
    }

    public function update(
        UpdateCampaignRequest $request,
        Campaign $campaign,
        UpdateCampaignAction $action
    ): JsonResponse {
        $campaign = $action->execute($campaign, $request->validated());

        return response()->json([
            'status' => true,
            'message' => 'Campaign updated successfully.',
            'data' => new CampaignResource($campaign->load('emailTemplate')),
        ], 200);
    }

    public function destroy(Campaign $campaign): JsonResponse
    {
        $campaign->delete();

        return response()->json([
            'status' => true,
            'message' => 'Campaign deleted successfully.',
        ], 200);
    }

    public function bulkDestroy(Request $request): JsonResponse
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer|exists:campaigns,id']);
        $deletedCount = Campaign::whereIn('id', $request->input('ids'))->delete();

        return response()->json([
            'status' => true,
            'message' => "{$deletedCount} campaigns deleted successfully.",
            'data' => ['deleted_count' => $deletedCount],
        ], 200);
    }

    public function updateStatus(Request $request, Campaign $campaign): JsonResponse
    {
        $request->validate(['is_active' => 'required|boolean']);
        $campaign->update(['is_active' => $request->input('is_active')]);

        return response()->json([
            'status' => true,
            'message' => 'Campaign status updated successfully.',
            'data' => new CampaignResource($campaign->load('emailTemplate')),
        ], 200);
    }

    public function events(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'message' => 'Campaign events retrieved successfully.',
            'data' => CampaignEvent::forApi(),
        ], 200);
    }
}
