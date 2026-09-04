<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Inertia\Inertia;

/** Mentions légales, confidentialité, cookies, conditions d'annulation. */
class PageController extends Controller
{
    public function show(Page $page)
    {
        abort_unless($page->is_published, 404);

        return Inertia::render('Client/Page', [
            'page' => $page->only(['slug', 'title', 'body']),
        ]);
    }
}
