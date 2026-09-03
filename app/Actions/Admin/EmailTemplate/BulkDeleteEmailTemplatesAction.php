<?php

namespace App\Actions\Admin\EmailTemplate;

use App\Models\EmailTemplate;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;

class BulkDeleteEmailTemplatesAction
{
    /**
     * Delete multiple email templates in a single transaction, rejecting deletion if any protected system template is selected.
     *
     * @param  list<int>  $ids
     * @return int Number of deleted email templates
     *
     * @throws HttpResponseException
     */
    public function execute(array $ids): int
    {
        $hasProtectedTemplate = EmailTemplate::query()
            ->whereIn('id', $ids)
            ->where('key', 'USER_ACCOUNT_CREATED')
            ->exists();

        if ($hasProtectedTemplate) {
            throw new HttpResponseException(
                response()->json([
                    'status' => false,
                    'message' => 'The selection includes system template USER_ACCOUNT_CREATED which is protected and cannot be deleted.',
                    'error' => 'Protected template in selection',
                ], 422)
            );
        }

        return DB::transaction(function () use ($ids) {
            return EmailTemplate::query()->whereIn('id', $ids)->delete();
        });
    }
}
