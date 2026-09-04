<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Inertia\Inertia;

/** « Qui suis-je » : page libre, éditable depuis le back office. */
class AboutController extends Controller
{
    public function index()
    {
        $page = Page::where('slug', 'qui-suis-je')->where('is_published', true)->first();

        return Inertia::render('Client/About', [
            'page' => $page ? [
                'title' => $page->title,
                'body'  => $page->body,
                'image' => $page->image_url,
            ] : null,
        ]);
    }
}
