<?php

namespace App\Support;
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use App\Support\AdSpace;
use Illuminate\Http\Request;

class AdSpaceController extends Controller
{
    public function index()
    {
        $spaces = AdSpace::spaces();

        // Calculate counts
        $totalCount  = count($spaces);
        $activeCount = count(array_filter($spaces, fn ($s) => !empty($s['enabled'])));

        // Direct campaign count
        $campaignCount = Advertisement::live()->count();

        // Group spaces by platform
        $groups = [
            'tanbat' => [
                'label'       => 'Tanbat Placements',
                'description' => 'Display ads, native banners, and sponsored elements shown across the Tanbat social web app.',
                'spaces'      => array_filter($spaces, fn ($s) => ($s['group'] ?? '') === 'tanbat'),
            ],
            'omrms' => [
                'label'       => 'OMRMS Placements',
                'description' => 'Ad units and native cards rendered on the omrms.com article and category sections.',
                'spaces'      => array_filter($spaces, fn ($s) => ($s['group'] ?? '') === 'omrms'),
            ],
            'global' => [
                'label'       => 'Global Ad Injections',
                'description' => 'Sitewide header and footer tags (Google AdSense Auto-Ads, verification scripts, popunders).',
                'spaces'      => array_filter($spaces, fn ($s) => ($s['group'] ?? '') === 'global'),
            ],
        ];

        return view('admin.ad-spaces.index', compact('groups', 'totalCount', 'activeCount', 'campaignCount'));
    }

    public function update(Request $request)
    {
        AdSpace::save($request->all());

        return redirect()->route('admin.ad-spaces.index')
            ->with('status', 'Ad spaces updated successfully.');
    }

    public function reset(Request $request)
    {
        $space = $request->input('space');
        AdSpace::reset($space ?: null);

        $msg = $space && isset(AdSpace::SPACES[$space])
            ? "Ad space '{$space}' reset to default settings."
            : 'All ad spaces reset to their default settings.';

        return redirect()->route('admin.ad-spaces.index')->with('status', $msg);
    }
}
