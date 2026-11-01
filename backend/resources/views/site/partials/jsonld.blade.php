<script type="application/ld+json">
{!! json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'MovingCompany',
    'name' => $company->company_name,
    'url' => url('/'),
    'logo' => asset('assets/video/hero-truck-poster.jpg'),
    'telephone' => $phoneTel,
    'email' => $site->email_info,
    'foundingDate' => '2020',
    'slogan' => 'Moving Goods, Building Trust, Delivering Excellence.',
    'address' => [
        '@type' => 'PostalAddress',
        'streetAddress' => 'Plot 6, Block 48, Olueasanya Street, off AIT Road, Kola Alagbado',
        'addressLocality' => 'Ifako-Ijaye',
        'addressRegion' => 'Lagos',
        'addressCountry' => 'NG',
    ],
    'areaServed' => $company->show_cross_border ? ['Nigeria', 'West Africa'] : ['Nigeria'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) !!}
</script>
