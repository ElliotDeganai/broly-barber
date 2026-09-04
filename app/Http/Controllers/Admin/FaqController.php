<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use Illuminate\Http\Request;
use Inertia\Inertia;

class FaqController extends Controller
{
    public function index()
    {
        return Inertia::render('Admin/Faqs', [
            'faqs' => Faq::orderBy('position')->get(['id', 'question', 'answer', 'is_published', 'position']),
        ]);
    }

    public function store(Request $request)
    {
        Faq::create($this->validated($request) + ['position' => (int) Faq::max('position') + 1]);

        return back()->with('success', 'Question ajoutée.');
    }

    public function update(Request $request, Faq $faq)
    {
        $faq->update($this->validated($request));

        return back()->with('success', 'Question enregistrée.');
    }

    public function destroy(Faq $faq)
    {
        $faq->delete();

        return back()->with('success', 'Question supprimée.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'question'     => ['required', 'string', 'max:255'],
            'answer'       => ['required', 'string', 'max:3000'],
            'is_published' => ['boolean'],
            'position'     => ['nullable', 'integer', 'min:0'],
        ]);
    }
}
