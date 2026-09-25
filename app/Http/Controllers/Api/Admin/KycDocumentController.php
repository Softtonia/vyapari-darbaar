<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CompanyKycDocument;
use Illuminate\Support\Str;

class KycDocumentController extends Controller
{
    /**
     * Batch upload KYC documents.
     */
    public function batchUpload(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'documents' => 'required|array',
            'documents.*.type' => 'required|string',
            'documents.*.file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB Max
        ]);

        $batchId = (string) Str::uuid();
        $companyId = $request->input('company_id');
        $uploadedDocs = [];

        foreach ($request->file('documents') as $index => $docData) {
            $type = $request->input("documents.{$index}.type");
            $file = $docData['file'];

            // Store file
            $path = $file->store("kyc/{$companyId}", 'public');

            // Save to DB
            $doc = CompanyKycDocument::create([
                'company_id' => $companyId,
                'document_type' => $type,
                'file_path' => $path,
                'status' => 'pending',
                'upload_batch_id' => $batchId,
            ]);

            $uploadedDocs[] = $doc;
        }

        return response()->json([
            'status' => true,
            'message' => 'Documents uploaded successfully.',
            'batch_id' => $batchId,
            'data' => $uploadedDocs,
        ]);
    }

    /**
     * Get batch progress.
     */
    public function batchProgress($batchId)
    {
        $documents = CompanyKycDocument::where('upload_batch_id', $batchId)->get();

        if ($documents->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'Batch not found.',
            ], 404);
        }

        $total = $documents->count();
        $completed = $documents->whereNotNull('file_path')->count();

        return response()->json([
            'status' => true,
            'batch_id' => $batchId,
            'progress' => ($total > 0) ? round(($completed / $total) * 100) : 0,
            'documents' => $documents,
        ]);
    }

    /**
     * Get all KYC documents for a company.
     */
    public function index(Request $request)
    {
        $request->validate(['company_id' => 'required|exists:companies,id']);
        
        $documents = CompanyKycDocument::where('company_id', $request->company_id)->get();
        
        return response()->json([
            'status' => true,
            'data' => $documents
        ]);
    }

    /**
     * Update KYC document status (verify/reject).
     */
    public function updateStatus(Request $request, $id)
    {
        $request->validate(['status' => 'required|in:pending,verified,rejected']);
        
        $document = CompanyKycDocument::findOrFail($id);
        $document->update(['status' => $request->status]);
        
        return response()->json([
            'status' => true,
            'message' => 'KYC document status updated successfully.',
            'data' => $document
        ]);
    }

    /**
     * Delete a KYC document.
     */
    public function destroy($id)
    {
        $document = CompanyKycDocument::findOrFail($id);
        
        // Optionally delete file from storage here
        // \Illuminate\Support\Facades\Storage::disk('public')->delete($document->file_path);
        
        $document->delete();
        
        return response()->json([
            'status' => true,
            'message' => 'KYC document deleted successfully.'
        ]);
    }
}
