<?php

namespace App\Http\Controllers;

use App\Models\CannedResponse;
use Illuminate\Http\Request;

class CannedResponseController extends Controller
{
    public function index()
    {
        $cannedResponses = CannedResponse::orderBy('title')->get();

        return view('admin.canned-responses', compact('cannedResponses'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:100',
            'body' => 'required|string|max:5000',
        ]);

        CannedResponse::create($data);

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Created :name', ['name' => __('Template')]),
        ]);

        return redirect()->route('admin.canned-responses.index');
    }

    public function destroy(string $cannedResponse)
    {
        CannedResponse::findOrFail($cannedResponse)->delete();

        session()->flash('toast', [
            'type' => 'success',
            'message' => __('Deleted :name', ['name' => __('Template')]),
        ]);

        return redirect()->route('admin.canned-responses.index');
    }
}
