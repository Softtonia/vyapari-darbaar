<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\NotificationTemplate;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class NotificationTemplateService
{
    /**
     * Supported standard campaign placeholders.
     *
     * @var list<string>
     */
    public const ALLOWED_CAMPAIGN_PLACEHOLDERS = [
        'user_name',
        'first_name',
        'last_name',
        'company_name',
        'date',
        'app_name',
    ];

    /**
     * Get paginated list of notification templates with filtering and search.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        $query = NotificationTemplate::query()->with('creator:id,first_name,last_name,name');

        if (! empty($filters['search'])) {
            $search = (string) $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('title', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status'] !== '') {
            $query->where('status', filter_var($filters['status'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['channel'])) {
            $query->where('channel', (string) $filters['channel']);
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'created_at');
        $allowedSorts = ['id', 'name', 'code', 'title', 'channel', 'status', 'created_at'];
        if (! in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'created_at';
        }

        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 20)));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Create a new notification template.
     *
     * @param  array<string, mixed>  $data
     * @param  Admin|null  $admin
     * @return NotificationTemplate
     */
    public function create(array $data, ?Admin $admin = null): NotificationTemplate
    {
        $data['created_by'] = $admin?->id;

        return NotificationTemplate::create($data);
    }

    /**
     * Update an existing notification template.
     *
     * @param  NotificationTemplate  $template
     * @param  array<string, mixed>  $data
     * @return NotificationTemplate
     */
    public function update(NotificationTemplate $template, array $data): NotificationTemplate
    {
        $template->update($data);

        return $template->fresh();
    }

    /**
     * Delete a notification template.
     *
     * @param  NotificationTemplate  $template
     * @return bool
     */
    public function delete(NotificationTemplate $template): bool
    {
        return (bool) $template->delete();
    }

    /**
     * Bulk delete notification templates.
     *
     * @param  array<int, int>  $ids
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        return NotificationTemplate::whereIn('id', $ids)->delete();
    }

    /**
     * Safely render text placeholders with supplied variables without eval.
     *
     * @param  string  $text
     * @param  array<string, string>  $variables
     * @param  bool  $removeUnknown
     * @return string
     */
    public function render(string $text, array $variables = [], bool $removeUnknown = false): string
    {
        if (trim($text) === '') {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($matches) use ($variables, $removeUnknown) {
            $key = $matches[1];
            if (array_key_exists($key, $variables)) {
                return (string) $variables[$key];
            }

            return $removeUnknown ? '' : $matches[0];
        }, $text) ?? $text;
    }

    /**
     * Build standard personalization variables for a recipient user.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $extraContext
     * @return array<string, string>
     */
    public function buildUserVariables(User $user, array $extraContext = []): array
    {
        $company = $user->company;

        $vars = [
            'user_name' => $user->name ?? $user->full_name,
            'first_name' => $user->first_name ?? explode(' ', (string) $user->name)[0] ?? '',
            'last_name' => $user->last_name ?? '',
            'company_name' => $company?->name ?? 'Vyapari Darbaar',
            'date' => now()->format('d M Y'),
            'app_name' => config('app.name', 'Vyapari Darbaar'),
        ];

        foreach ($extraContext as $k => $v) {
            if (is_scalar($v)) {
                $vars[(string) $k] = (string) $v;
            }
        }

        return $vars;
    }

    /**
     * Extract placeholder keys from text.
     *
     * @param  string  $text
     * @return list<string>
     */
    public function extractPlaceholders(string $text): array
    {
        if (preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $text, $matches)) {
            return array_values(array_unique($matches[1]));
        }

        return [];
    }

    /**
     * Find unsupported placeholders that cannot be resolved in generic campaign context.
     *
     * @param  string  $text
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public function findUnsupportedPlaceholders(string $text, array $allowed = self::ALLOWED_CAMPAIGN_PLACEHOLDERS): array
    {
        $extracted = $this->extractPlaceholders($text);

        return array_values(array_diff($extracted, $allowed));
    }
}
