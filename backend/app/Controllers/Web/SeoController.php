<?php
declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Services\SitemapService;

/**
 * SEO 端点：robots.txt + sitemap.xml
 */
final class SeoController
{
    /** GET /robots.txt */
    public function robots(Request $req, Response $res, array $params): void
    {
        $res->header('Content-Type', 'text/plain; charset=utf-8');
        $res->text(SitemapService::robots());
    }

    /** GET /sitemap.xml */
    public function sitemap(Request $req, Response $res, array $params): void
    {
        $res->header('Content-Type', 'application/xml; charset=utf-8');
        $res->text(SitemapService::render());
    }
}
