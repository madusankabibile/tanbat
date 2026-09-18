<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use Illuminate\Http\RedirectResponse;

class AdvertisementClickController extends Controller
{
    /**
     * Track a click on a direct campaign ad and forward to destination URL.
     */
    public function click(Advertisement $ad): RedirectResponse
    {
        $ad->recordClick();

        $target = $ad->link_url ?: url('/');
        return redirect()->away($target);
    }
}
