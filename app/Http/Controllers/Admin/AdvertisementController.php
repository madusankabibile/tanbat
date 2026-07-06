<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdvertisementController extends Controller
{
    private const PLACEMENTS = ['assistant', 'sidebar', 'feed', 'banner'];

    public function index(Request $request)
    {
        $q         = trim((string) $request->query('q', ''));
        $placement = $request->query('placement');
        $status    = $request->query('status'); // active|inactive|live|expired

        $query = Advertisement::query();
        if ($q !== '') {
            $like = '%' . $q . '%';
            $query->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('link_url', 'like', $like));
        }
        if (in_array($placement, self::PLACEMENTS, true)) {
            $query->where('placement', $placement);
        }
        switch ($status) {
            case 'active':   $query->where('is_active', true); break;
            case 'inactive': $query->where('is_active', false); break;
            case 'live':     $query->live(); break;
            case 'expired':  $query->whereNotNull('ends_at')->where('ends_at', '<', now()); break;
        }

        $ads = $query->latest()->paginate(20)->withQueryString();
        $placements = self::PLACEMENTS;

        return view('admin.ads.index', compact('ads', 'q', 'placement', 'status', 'placements'));
    }

    public function create()
    {
        $placements = self::PLACEMENTS;
        $ad = new Advertisement(['is_active' => true, 'weight' => 1, 'placement' => 'assistant']);
        return view('admin.ads.edit', ['ad' => $ad, 'placements' => $placements, 'isNew' => true]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('ads', 'public');
        }
        $ad = Advertisement::create($data);
        return redirect()->route('admin.ads.edit', $ad)->with('status', 'Advertisement created.');
    }

    public function edit(Advertisement $ad)
    {
        $placements = self::PLACEMENTS;
        return view('admin.ads.edit', ['ad' => $ad, 'placements' => $placements, 'isNew' => false]);
    }

    public function update(Request $request, Advertisement $ad)
    {
        $data = $this->validated($request);

        if ($request->boolean('remove_image') && $ad->image) {
            Storage::disk('public')->delete($ad->image);
            $data['image'] = null;
        }
        if ($request->hasFile('image')) {
            if ($ad->image) {
                Storage::disk('public')->delete($ad->image);
            }
            $data['image'] = $request->file('image')->store('ads', 'public');
        }

        $ad->update($data);
        return redirect()->route('admin.ads.edit', $ad)->with('status', 'Advertisement updated.');
    }

    public function toggle(Advertisement $ad)
    {
        $ad->update(['is_active' => !$ad->is_active]);
        return back()->with('status', $ad->is_active ? 'Activated.' : 'Deactivated.');
    }

    public function destroy(Advertisement $ad)
    {
        if ($ad->image) {
            Storage::disk('public')->delete($ad->image);
        }
        $ad->delete();
        return redirect()->route('admin.ads.index')->with('status', 'Advertisement deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title'     => ['required', 'string', 'max:160'],
            'body'      => ['nullable', 'string', 'max:2000'],
            'link_url'  => ['required', 'url', 'max:500'],
            'placement' => ['required', Rule::in(self::PLACEMENTS)],
            'is_active' => ['nullable', 'boolean'],
            'weight'    => ['nullable', 'integer', 'min:1', 'max:1000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at'   => ['nullable', 'date', 'after_or_equal:starts_at'],
            'image'     => ['nullable', 'image', 'max:4096'],
        ]) + ['is_active' => $request->boolean('is_active'), 'weight' => (int) ($request->input('weight') ?: 1)];
    }
}
