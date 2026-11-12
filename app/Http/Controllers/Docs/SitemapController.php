<?php

namespace App\Http\Controllers\Docs;

use App\Models\System;
use Illuminate\Http\Response;

/**
 * sitemap.xml for search engines: public manuals and their non-restricted sections only.
 */
class SitemapController extends DocsController
{
    public function index(): Response
    {
        $urls = [['loc' => $this->context()->home(), 'lastmod' => null]];

        $systems = System::query()
            ->where('organisation_id', $this->context()->organisation->id)
            ->where('library_visibility', System::LIBRARY_PUBLIC)
            ->with('latestRelease')
            ->get();

        foreach ($systems as $system) {
            $urls[] = ['loc' => $this->context()->system($system), 'lastmod' => $system->latestRelease?->published_at];

            $outline = $this->outline($system);
            $restricted = $this->access()->protectedSectionIds($outline);

            foreach ($outline as $section) {
                if (!isset($restricted[$section->id])) {
                    $urls[] = ['loc' => $this->context()->section($system, $section), 'lastmod' => $section->currentRevision?->published_at];
                }
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url><loc>' . e($url['loc']) . '</loc>'
                . ($url['lastmod'] ? '<lastmod>' . $url['lastmod']->toDateString() . '</lastmod>' : '')
                . '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
