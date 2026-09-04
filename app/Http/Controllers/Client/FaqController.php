<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\Faq;
use App\Models\SiteSetting;
use Inertia\Inertia;

class FaqController extends Controller
{
    public function index()
    {
        return Inertia::render('Client/Faq', [
            'content' => SiteSetting::tree('faq'),
            'faqs'    => Faq::published()->get(['id', 'question', 'answer']),
        ]);
    }
}
