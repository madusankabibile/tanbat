<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BookDetail;
use App\Services\BookRssImporter;
use App\Support\BookRssSettings;
use Illuminate\Http\Request;

/**
 * Admin screen for the book RSS importer.
 *
 *   GET  /admin/book-rss          → feed settings, last run, recent imports
 *   PUT  /admin/book-rss          → save the feed URL
 *   POST /admin/book-rss/toggle   → enable / disable the automatic poll
 *   POST /admin/book-rss/import   → run an import right now
 *
 * The automatic poll runs from the heartbeat (TaskRunnerController); this page
 * is the manual override and the place to watch what it's doing.
 */
class BookRssController extends Controller
{
    public function index()
    {
        $lastRun = BookRssSettings::lastRunAt();

        // Books that came from the feed carry a source_url; that's what makes
        // an imported row distinguishable from a wizard- or admin-made one.
        $recent = BookDetail::query()
            ->whereNotNull('source_url')
            ->with('post:id,user_id')
            ->latest('id')
            ->limit(15)
            ->get();

        return view('admin.book-rss', [
            'enabled'     => BookRssSettings::enabled(),
            'feedUrl'     => BookRssSettings::feedUrl(),
            'lastRunAt'   => $lastRun ? \Illuminate\Support\Carbon::createFromTimestamp($lastRun) : null,
            'lastResult'  => BookRssSettings::lastResult(),
            'pollMinutes' => (int) config('book_rss.poll_interval_minutes', 60),
            'maxPerRun'   => (int) config('book_rss.max_per_run', 10),
            'importedTotal' => BookDetail::whereNotNull('source_url')->count(),
            'recent'      => $recent,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'feed_url' => ['required', 'url', 'max:1024'],
        ]);

        BookRssSettings::setFeedUrl($data['feed_url']);

        return redirect()->route('admin.book-rss.index')
            ->with('status', 'Feed URL saved.');
    }

    public function toggle(Request $request)
    {
        $on = $request->boolean('enabled');
        BookRssSettings::setEnabled($on);

        return back()->with('status', $on
            ? 'Automatic feed import enabled.'
            : 'Automatic feed import disabled — you can still import manually.');
    }

    /**
     * Import right now, ignoring the enabled toggle and the poll interval.
     * Feed fetch + cover downloads can take a while for a full batch, so the
     * request gets extra headroom.
     */
    public function import()
    {
        @set_time_limit(300);

        try {
            $result = (new BookRssImporter())->import();
        } catch (\Throwable $e) {
            BookRssSettings::recordRun('Failed: ' . $e->getMessage());
            return back()->with('error', 'Import failed: ' . $e->getMessage());
        }

        $summary = "{$result['created']} created, {$result['skipped']} already known, {$result['failed']} failed";
        BookRssSettings::recordRun($summary);

        if ($result['created'] === 0) {
            return back()->with('status', "Nothing new — {$summary}.");
        }

        return back()->with('status', "Imported {$result['created']} book(s): "
            . implode(', ', array_slice($result['titles'], 0, 5))
            . (count($result['titles']) > 5 ? '…' : ''));
    }
}
