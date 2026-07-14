{!! '<?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($urls as $url)
  <url>
    <loc>{{ $url['loc'] }}</loc>
@if ($url['lastmod'])
    <lastmod>{{ $url['lastmod'] }}</lastmod>
@endif
    <changefreq>{{ $url['priority'] === '1.0' ? 'daily' : 'weekly' }}</changefreq>
    <priority>{{ $url['priority'] }}</priority>
  </url>
@endforeach
</urlset>
