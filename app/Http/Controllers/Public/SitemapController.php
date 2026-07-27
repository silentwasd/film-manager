<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Company;
use App\Models\Film;
use App\Models\Person;
use Illuminate\Support\Facades\Cache;
use SimpleXMLElement;

class SitemapController extends Controller
{
    public function index()
    {
        $xml = Cache::remember('sitemap', 3600, function () {
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"/>');

            $films = Film::all();

            foreach ($films as $film) {
                $url = $xml->addChild('url');
                $url->addChild('loc', config('app.frontend_url') . '/catalog/films/' . $film->id);
                $url->addChild('lastmod', $film->updated_at->format('Y-m-d'));
                $url->addChild('changefreq', 'daily');
                $url->addChild('priority', 1);
            }

            $people = Person::all();

            foreach ($people as $person) {
                $url = $xml->addChild('url');
                $url->addChild('loc', config('app.frontend_url') . '/catalog/people/' . $person->id);
                $url->addChild('lastmod', $person->updated_at->format('Y-m-d'));
                $url->addChild('changefreq', 'daily');
                $url->addChild('priority', 1);
            }

            $companies = Company::all();

            foreach ($companies as $company) {
                $url = $xml->addChild('url');
                $url->addChild('loc', config('app.frontend_url') . '/catalog/companies/' . $company->id);
                $url->addChild('lastmod', $company->updated_at->format('Y-m-d'));
                $url->addChild('changefreq', 'daily');
                $url->addChild('priority', 1);
            }

            $collections = Collection::query()->public()->get();

            foreach ($collections as $collection) {
                $url = $xml->addChild('url');
                $url->addChild('loc', config('app.frontend_url') . '/collections/' . $collection->public_key);
                $url->addChild('lastmod', $collection->updated_at->format('Y-m-d'));
                $url->addChild('changefreq', 'weekly');
                $url->addChild('priority', 1);
            }

            return $xml->asXML();
        });

        return response($xml, headers: [
            'Content-Type' => 'application/xml'
        ]);
    }
}
