<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\BookSearch;
use Illuminate\Http\Request;

/**
 * Admin-only "Book Search Engine" settings.
 *
 *   GET  /admin/book-search   → pick the active engine + edit each domain
 *   PUT  /admin/book-search   → persist the selection and domains
 *
 * The selection drives both book SEARCH and book PUBLISH in
 * App\Http\Controllers\AssistantController. Domains are editable because both
 * sources rotate their public hostnames periodically.
 */
class BookSearchController extends Controller
{
    public function index()
    {
        $engines = [];
        foreach (BookSearch::ENGINES as $slug => $meta) {
            $engines[$slug] = [
                'label'          => $meta['label'],
                'default_domain' => $meta['default_domain'],
                'domain'         => BookSearch::domain($slug),
            ];
        }

        return view('admin.book-search', [
            'engines' => $engines,
            'active'  => BookSearch::engine(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'engine'        => 'required|in:' . implode(',', array_keys(BookSearch::ENGINES)),
            'annas_domain'  => 'required|string|max:255',
            'zlib_domain'   => 'required|string|max:255',
        ]);

        BookSearch::setEngine($data['engine']);
        BookSearch::setDomain('annas', $data['annas_domain']);
        BookSearch::setDomain('zlib', $data['zlib_domain']);

        $label = BookSearch::ENGINES[$data['engine']]['label'];

        return redirect()->route('admin.book-search.index')
            ->with('status', "Saved — book searches now use {$label}.");
    }
}
