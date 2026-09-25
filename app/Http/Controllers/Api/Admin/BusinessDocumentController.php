<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BusinessDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BusinessDocumentController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        $query = BusinessDocument::query();

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        $documents = $query->get();

        return response()->json([
            'success' => true,
            'data' => $documents
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'company_id' => 'required|exists:companies,id',
            'user_id' => 'nullable|exists:users,id',
            'document_type' => 'required|string|max:100',
            'file' => 'required|file|mimes:jpeg,png,jpg,pdf|max:5120',
        ]);

        $path = $request->file('file')->store('business-documents', 'public');

        $document = BusinessDocument::create([
            'company_id' => $request->company_id,
            'user_id' => $request->user_id,
            'document_type' => $request->document_type,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Business document uploaded successfully.',
            'data' => $document
        ], 201);
    }

    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,verified,rejected',
        ]);

        $document = BusinessDocument::findOrFail($id);
        $document->update(['status' => $request->status]);

        return response()->json([
            'success' => true,
            'message' => 'Business document status updated successfully.',
            'data' => $document
        ]);
    }

    public function destroy($id)
    {
        $document = BusinessDocument::findOrFail($id);

        if (Storage::disk('public')->exists($document->file_path)) {
            Storage::disk('public')->delete($document->file_path);
        }

        $document->delete();

        return response()->json([
            'success' => true,
            'message' => 'Business document deleted successfully.'
        ]);
    }
}
