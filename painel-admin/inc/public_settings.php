<?php
declare(strict_types=1);

/** Chaves de configuração que podem ser expostas publicamente no site. */
function public_setting_keys(): array
{
    return [
        'company_name', 'phone_main', 'whatsapp', 'email_orcamento', 'email_atendimento', 'email_rh', 'email_vagas',
        'address_macae', 'address_rio', 'support_cities', 'social_instagram', 'social_facebook', 'social_linkedin',
        'footer_text', 'stat_years', 'stat_employees', 'url_courses',
    ];
}

function public_settings(): array
{
    $out = [];
    foreach (public_setting_keys() as $k) {
        $out[$k] = setting($k);
    }
    return $out;
}

/** Monta a configuração de rastreamento validando cada ID (evita injeção por campo de ID). */
function tracking_config(): array
{
    if (setting('trk_enabled') !== '1') {
        return ['enabled' => false];
    }
    $gtm = strtoupper(trim(setting('trk_gtm')));
    $ga4 = strtoupper(trim(setting('trk_ga4')));
    $pix = trim(setting('trk_meta_pixel'));
    $ads = strtoupper(trim(setting('trk_google_ads')));
    return [
        'enabled'    => true,
        'gtm'        => preg_match('/^GTM-[A-Z0-9]{4,12}$/', $gtm) ? $gtm : '',
        'ga4'        => preg_match('/^G-[A-Z0-9]{4,14}$/', $ga4) ? $ga4 : '',
        'meta_pixel' => preg_match('/^\d{6,20}$/', $pix) ? $pix : '',
        'google_ads' => preg_match('/^AW-\d{6,14}$/', $ads) ? $ads : '',
        'head'       => setting('trk_head'),
        'body_start' => setting('trk_body_start'),
        'body_end'   => setting('trk_body_end'),
    ];
}
