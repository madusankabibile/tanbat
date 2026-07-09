<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Synthesises a blog article from a title using the Groq LLM (OpenAI-compatible
 * chat-completions API). Used to back old /read-blog/{id}_{slug}.html URLs whose
 * original content is gone — we generate once, persist, and never regenerate.
 *
 * generate() returns a structured array (title/excerpt/tags/category/body HTML)
 * or null on any failure, so callers can decide not to persist and retry later.
 */
class ArticleGenerator
{
    /** @return array{title:string,excerpt:string,tags:array<int,string>,category:?string,body:string,model:string}|null */
    public function generate(string $title): ?array
    {
        $title = trim($title);
        if ($title === '') {
            return null;
        }

        $cfg = config('services.groq');
        if (empty($cfg['key'])) {
            Log::warning('read-blog: GROQ_API_KEY not set; cannot generate article', ['title' => $title]);
            return null;
        }

        try {
            $resp = Http::withToken($cfg['key'])
                ->withOptions(['verify' => false])
                ->timeout((int) ($cfg['timeout'] ?? 25))
                ->acceptJson()
                ->post($cfg['base_url'] . '/chat/completions', [
                    'model'           => $cfg['model'],
                    'temperature'     => 0.6,
                    'max_tokens'      => 2200,
                    'response_format' => ['type' => 'json_object'],
                    'messages'        => [
                        ['role' => 'system', 'content' => $this->systemPrompt()],
                        ['role' => 'user',   'content' => $this->userPrompt($title)],
                    ],
                ]);

            if (!$resp->ok()) {
                Log::warning('read-blog: groq call failed', ['status' => $resp->status(), 'body' => $resp->body()]);
                return null;
            }

            $content = (string) data_get($resp->json(), 'choices.0.message.content', '');
            if ($content === '') {
                return null;
            }

            $data = json_decode($content, true);
            if (!is_array($data)) {
                return null;
            }

            // The model is not perfectly consistent — any field may come back as
            // a string OR a nested array of strings. Coerce everything safely.
            $bodyHtml = $this->sanitizeHtml($this->toStr($data['body_html'] ?? $data['body'] ?? '', "\n"));
            if ($bodyHtml === '') {
                return null;
            }

            $cleanTitle = trim($this->toStr($data['title'] ?? '')) ?: $title;
            $excerpt    = Str::of(strip_tags($this->toStr($data['excerpt'] ?? $bodyHtml)))->squish()->limit(200);

            $tags = [];
            foreach ((array) ($data['tags'] ?? []) as $t) {
                $t = trim($this->toStr($t), " \t\n\r\0\x0B#");
                if ($t !== '') {
                    $tags[] = Str::limit($t, 40, '');
                }
            }
            $tags = array_slice(array_values(array_unique($tags)), 0, 6);

            $category = trim($this->toStr($data['category'] ?? ''));
            $category = $category !== '' ? Str::limit($category, 60, '') : null;

            return [
                'title'    => Str::limit($cleanTitle, 500, ''),
                'excerpt'  => (string) $excerpt,
                'tags'     => $tags,
                'category' => $category,
                'body'     => $bodyHtml,
                'model'    => (string) $cfg['model'],
            ];
        } catch (\Throwable $e) {
            Log::warning('read-blog: groq exception', ['title' => $title, 'error' => $e->getMessage()]);
            return null;
        }
    }

    private function systemPrompt(): string
    {
        return implode(' ', [
            'You are a professional blog writer for a general-interest publication.',
            'Given a title, write an original, informative, well-structured article of roughly 500-800 words.',
            'Use a neutral, factual, human tone. Do not invent specific statistics, dates, prices, named people, or quotes;',
            'keep claims general and reasonable. Respond ONLY with a single JSON object with these keys:',
            '"title" (a clean, properly capitalised version of the title),',
            '"excerpt" (one plain-text sentence, max 30 words),',
            '"category" (one or two words),',
            '"tags" (array of 3 to 6 short lowercase topic tags),',
            'and "body_html" (the article body as clean semantic HTML using only',
            '<h2>, <h3>, <p>, <ul>, <ol>, <li>, <blockquote>, <strong>, and <em> tags —',
            'no <script>, <style>, <img>, inline styles, or <h1>). Do not wrap the JSON in markdown fences.',
        ]);
    }

    private function userPrompt(string $title): string
    {
        return 'Write the article for this title: "' . $title . '"';
    }

    /**
     * Coerce a model-returned value to a string. Fields occasionally arrive as
     * nested arrays (e.g. body_html as an array of paragraphs), so flatten any
     * array to its scalar leaves joined by $glue instead of casting (which would
     * throw "Array to string conversion").
     */
    private function toStr(mixed $v, string $glue = ' '): string
    {
        if (is_string($v)) {
            return $v;
        }
        if (is_scalar($v)) {
            return (string) $v;
        }
        if (is_array($v)) {
            $parts = [];
            array_walk_recursive($v, function ($x) use (&$parts) {
                if (is_scalar($x)) {
                    $parts[] = (string) $x;
                }
            });
            return implode($glue, $parts);
        }
        return '';
    }

    /**
     * Keep only a safe subset of formatting tags and strip any attributes /
     * dangerous elements. The content is machine-generated and rendered with
     * {!! !!}, so this is a defensive allow-list, not full HTML purification.
     */
    private function sanitizeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        // Drop code that could execute or leak, tags and all.
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|link|meta)\b[^>]*>.*?</\1>#is', '', $html) ?? $html;
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|link|meta)\b[^>]*/?>#is', '', $html) ?? $html;

        $allowed = '<h2><h3><p><ul><ol><li><blockquote><strong><em><b><i><br>';
        $html = strip_tags($html, $allowed);

        // Strip every attribute (incl. on* handlers, style) from the surviving tags.
        $html = preg_replace('#<([a-z0-9]+)\b[^>]*>#i', '<$1>', $html) ?? $html;

        return trim($html);
    }
}
