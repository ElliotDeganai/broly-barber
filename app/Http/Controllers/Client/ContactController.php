<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Inertia\Inertia;

/**
 * Contact. L'adresse exacte n'est PAS publiée : la FAQ précise qu'elle est
 * communiquée à la confirmation du rendez-vous, le studio étant privé.
 */
class ContactController extends Controller
{
    public function index()
    {
        $settings = SiteSetting::all_cached();

        return Inertia::render('Client/Contact', [
            'content'  => SiteSetting::tree('contact'),
            'channels' => array_values(array_filter([
                $settings['instagram_url'] ?? null ? [
                    'type' => 'instagram', 'label' => $settings['instagram_handle'] ?? 'Instagram',
                    'url'  => $settings['instagram_url'],
                ] : null,
                $settings['tiktok_url'] ?? null ? [
                    'type' => 'tiktok', 'label' => $settings['tiktok_handle'] ?? 'TikTok',
                    'url'  => $settings['tiktok_url'],
                ] : null,
                $settings['contact_email'] ?? null ? [
                    'type' => 'email', 'label' => $settings['contact_email'],
                    'url'  => 'mailto:' . $settings['contact_email'],
                ] : null,
            ])),
        ]);
    }
}
