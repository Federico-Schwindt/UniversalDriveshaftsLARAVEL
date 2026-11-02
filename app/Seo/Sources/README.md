Sitemap sources. Every class in this folder with a public `urls(): array` method is picked up by
`SeoController::sitemap()` automatically. Return rows like
`['loc' => '/driveshafts/honda/pilot', 'pri' => '0.7', 'freq' => 'weekly', 'lastmod' => '2026-10-17']`
(`loc` is a site-relative path; `lastmod` optional, Y-m-d). Keep it fast or cache it: the sitemap is cached 6 h.
