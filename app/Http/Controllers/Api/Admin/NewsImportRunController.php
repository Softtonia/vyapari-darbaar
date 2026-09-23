<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\NewsImportStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NewsImport\ListNewsImportRunRequest;
use App\Http\Resources\NewsImportRunResource;
use App\Jobs\ProcessPibRssImportJob;
use App\Models\Admin;
use App\Models\NewsImportRun;
use App\Services\PibNewsImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsImportRunController extends Controller
{
    /**
     * List paginated PIB import run history with filters.
     *
     * GET /api/admin/news-import/runs
     *
     * Filters: status, source_id, date_from, date_to
     * Permission: news-import.view
     */
    public function index(ListNewsImportRunRequest $request): JsonResponse
    {
        $this->authorizeAdmin($request, 'news-import.view');

        $filters = $request->validated();

        $query = NewsImportRun::query()
            ->with(['source:id,name,code,slug', 'category:id,name,slug'])
            ->orderBy(
                in_array($filters['sort_by'] ?? null, NewsImportRun::ALLOWED_SORT_COLUMNS)
                    ? $filters['sort_by']
                    : 'id',
                strtolower($filters['sort_order'] ?? 'desc') === 'asc' ? 'asc' : 'desc'
            );

        if (! empty($filters['status'])) {
            $query->forStatus($filters['status']);
        }

        if (! empty($filters['source_id'])) {
            $query->forSource((int) $filters['source_id']);
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $perPage = min((int) ($filters['per_page'] ?? 15), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'status'  => true,
            'message' => 'News import runs fetched successfully.',
            'data'    => [
                'items'      => NewsImportRunResource::collection($paginator->items()),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ], 200);
    }

    /**
     * Show a single import run with full detail.
     *
     * GET /api/admin/news-import/runs/{run}
     *
     * Permission: news-import.view
     */
    public function show(Request $request, NewsImportRun $run): JsonResponse
    {
        $this->authorizeAdmin($request, 'news-import.view');

        $run->loadMissing([
            'source:id,name,code,slug',
            'category:id,name,slug',
            'triggeredBy:id,name,email',
        ]);

        return response()->json([
            'status'  => true,
            'message' => 'News import run details retrieved successfully.',
            'data'    => new NewsImportRunResource($run),
        ], 200);
    }

    /**
     * Trigger a new PIB RSS import run.
     *
     * POST /api/admin/news-import/runs/trigger
     *
     * Flow:
     *   1. Create NewsImportRun record (status = pending) — audit record exists before job
     *   2. Dispatch ProcessPibRssImportJob(run_id) to 'news-import' queue
     *   3. Return 202 Accepted with run_id
     *
     * Permission: news-import.trigger
     *
     * Note: The Redis lock inside the job prevents actual concurrent processing.
     * Rapid successive API calls will each get their own run record, but only
     * one job will acquire the lock and process; others will be marked skipped.
     */
    public function trigger(Request $request, PibNewsImportService $service): JsonResponse
    {
        $this->authorizeAdmin($request, 'news-import.trigger');

        if (! config('news_imports.pib.enabled', true)) {
            return response()->json([
                'status'  => false,
                'message' => 'PIB RSS import is currently disabled.',
            ], 422);
        }

        /** @var Admin|null $admin */
        $admin = $request->user();

        $run = $service->createPendingRun(triggeredBy: $admin?->id);

        ProcessPibRssImportJob::dispatch($run->id);

        return response()->json([
            'status'  => true,
            'message' => 'PIB news import queued successfully.',
            'data'    => [
                'run_id' => $run->id,
                'status' => NewsImportStatus::PENDING->value,
            ],
        ], 202);
    }

    /**
     * Authorize admin permissions with admin/super_admin role fallback.
     * Mirrors existing project convention from MarketIngestionRunController.
     */
    protected function authorizeAdmin(Request $request, string $permission): void
    {
        /** @var Admin|null $admin */
        $admin = $request->user();
        if ($admin && ! $admin->can($permission) && ! $admin->hasRole('admin') && ! $admin->hasRole('super_admin')) {
            abort(response()->json([
                'status'  => false,
                'message' => "Unauthorized action. Missing {$permission} permission.",
            ], 403));
        }
    }
}
