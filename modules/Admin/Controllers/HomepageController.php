<?php
namespace Modules\Admin\Controllers;

use Core\Container;
use Core\Request;
use Core\Response;
use Core\Csrf;
use App\Services\SettingsService;

class HomepageController
{
    private Container $container;
    private SettingsService $settings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->settings = $container->get(SettingsService::class);
    }

    public function index(Request $request): Response
    {
        $cfg = $this->settings->all();
        $data = [
            'title' => 'Homepage Builder',
            'csrf' => Csrf::token('home_builder'),
            'settings' => $cfg,
            'saved' => !empty($request->query['saved']),
        ];
        $html = $this->container->get('renderer')->render('admin/homepage', $data);
        return new Response($html);
    }

    public function save(Request $request): Response
    {
        if (!Csrf::check('home_builder', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF', 400);
        }
        $pairs = [
            'home_hero_title' => trim($request->body['home_hero_title'] ?? ''),
            'home_hero_eyebrow_ru' => trim($request->body['home_hero_eyebrow_ru'] ?? ''),
            'home_hero_eyebrow_en' => trim($request->body['home_hero_eyebrow_en'] ?? ''),
            'home_hero_subtitle' => trim($request->body['home_hero_subtitle'] ?? ''),
            'home_hero_cta_text' => trim($request->body['home_hero_cta_text'] ?? ''),
            'home_hero_cta_url' => trim($request->body['home_hero_cta_url'] ?? ''),
            'home_hero_badge' => trim($request->body['home_hero_badge'] ?? ''),
            'home_hero_background' => trim($request->body['home_hero_background'] ?? ''),
            'home_hero_overlay' => (string)min(max((float)($request->body['home_hero_overlay'] ?? 0.4), 0), 1),
            'home_hero_align' => in_array($request->body['home_hero_align'] ?? 'left', ['left','center','right'], true) ? $request->body['home_hero_align'] : 'left',
            'home_show_secondary_cta' => isset($request->body['home_show_secondary_cta']) ? '1' : '0',
            'home_secondary_cta_text' => trim($request->body['home_secondary_cta_text'] ?? ''),
            'home_secondary_cta_url' => trim($request->body['home_secondary_cta_url'] ?? ''),
            'home_layout_mode' => in_array($request->body['home_layout_mode'] ?? 'wide', ['wide','boxed'], true) ? $request->body['home_layout_mode'] : 'wide',
            'home_section_padding' => (int)($request->body['home_section_padding'] ?? 80),
            'home_gallery_style' => in_array($request->body['home_gallery_style'] ?? 'lightbox', ['lightbox','page'], true) ? $request->body['home_gallery_style'] : 'lightbox',
            'home_show_stats' => isset($request->body['home_show_stats']) ? '1' : '0',
            'home_stats_gallery_label_ru' => trim($request->body['home_stats_gallery_label_ru'] ?? ''),
            'home_stats_gallery_label_en' => trim($request->body['home_stats_gallery_label_en'] ?? ''),
            'home_stats_articles_label_ru' => trim($request->body['home_stats_articles_label_ru'] ?? ''),
            'home_stats_articles_label_en' => trim($request->body['home_stats_articles_label_en'] ?? ''),
            'home_show_gallery' => isset($request->body['home_show_gallery']) ? '1' : '0',
            'home_gallery_limit' => (int)($request->body['home_gallery_limit'] ?? 6),
            'home_gallery_title_ru' => trim($request->body['home_gallery_title_ru'] ?? ''),
            'home_gallery_title_en' => trim($request->body['home_gallery_title_en'] ?? ''),
            'home_gallery_cta_ru' => trim($request->body['home_gallery_cta_ru'] ?? ''),
            'home_gallery_cta_en' => trim($request->body['home_gallery_cta_en'] ?? ''),
            'home_show_articles' => isset($request->body['home_show_articles']) ? '1' : '0',
            'home_articles_limit' => (int)($request->body['home_articles_limit'] ?? 3),
            'home_articles_title_ru' => trim($request->body['home_articles_title_ru'] ?? ''),
            'home_articles_title_en' => trim($request->body['home_articles_title_en'] ?? ''),
            'home_articles_cta_ru' => trim($request->body['home_articles_cta_ru'] ?? ''),
            'home_articles_cta_en' => trim($request->body['home_articles_cta_en'] ?? ''),
            'home_order_gallery' => (int)($request->body['home_order_gallery'] ?? 1),
            'home_order_articles' => (int)($request->body['home_order_articles'] ?? 2),
            'home_custom_blocks' => $request->body['home_custom_blocks'] ?? '',
            'home_custom_css' => $request->body['home_custom_css'] ?? '',
            'home_custom_blocks_title_ru' => trim($request->body['home_custom_blocks_title_ru'] ?? ''),
            'home_custom_blocks_title_en' => trim($request->body['home_custom_blocks_title_en'] ?? ''),
            'home_custom_block_cta_ru' => trim($request->body['home_custom_block_cta_ru'] ?? ''),
            'home_custom_block_cta_en' => trim($request->body['home_custom_block_cta_en'] ?? ''),
            'footer_col1_title_ru' => trim($request->body['footer_col1_title_ru'] ?? ''),
            'footer_col1_title_en' => trim($request->body['footer_col1_title_en'] ?? ''),
            'footer_col1_body_ru' => $request->body['footer_col1_body_ru'] ?? '',
            'footer_col1_body_en' => $request->body['footer_col1_body_en'] ?? '',
            'footer_col2_title_ru' => trim($request->body['footer_col2_title_ru'] ?? ''),
            'footer_col2_title_en' => trim($request->body['footer_col2_title_en'] ?? ''),
            'footer_col2_body_ru' => $request->body['footer_col2_body_ru'] ?? '',
            'footer_col2_body_en' => $request->body['footer_col2_body_en'] ?? '',
            'footer_col3_title_ru' => trim($request->body['footer_col3_title_ru'] ?? ''),
            'footer_col3_title_en' => trim($request->body['footer_col3_title_en'] ?? ''),
            'footer_col3_body_ru' => $request->body['footer_col3_body_ru'] ?? '',
            'footer_col3_body_en' => $request->body['footer_col3_body_en'] ?? '',
        ];
        $this->settings->bulkSet($pairs);
        $prefix = $this->container->get('config')['admin_prefix'] ?? '/admin';
        return new Response('', 302, ['Location' => $prefix . '/homepage?saved=1']);
    }
}
