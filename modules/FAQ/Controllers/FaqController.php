<?php
namespace Modules\FAQ\Controllers;

use Core\Container;
use Core\Meta\CommonSchemas;
use Core\Meta\JsonLdRenderer;
use Core\ModuleSettings;
use Core\Request;
use Core\Response;
use Modules\FAQ\Models\FaqModel;

class FaqController
{
    private Container $container;
    private FaqModel $model;
    private ModuleSettings $moduleSettings;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->model = new FaqModel($container->get(\Core\Database::class));
        $this->moduleSettings = $container->get(ModuleSettings::class);
    }

    public function index(Request $request): Response
    {
        $items = $this->normalizeItems($this->model->published());
        $topicGroups = $this->buildTopicGroups($items);
        $canonical = $this->canonical($request);
        $seo = $this->resolveSeo($request);
        $faqSchema = $this->buildFaqSchema($items);
        $breadcrumbSchema = CommonSchemas::breadcrumbList([
            ['name' => 'Home', 'url' => $this->baseUrl($request) . '/'],
            ['name' => 'FAQ', 'url' => $canonical],
        ]);
        $jsonLd = JsonLdRenderer::render(JsonLdRenderer::merge($faqSchema, $breadcrumbSchema));

        $html = $this->container->get('renderer')->render(
            '@FAQ/public/index',
            [
                '_layout' => true,
                'title' => $seo['title'],
                'items' => $items,
                'topicGroups' => $topicGroups,
                'breadcrumbs' => [
                    ['label' => 'FAQ'],
                ],
            ],
            [
                'title' => $seo['title'],
                'canonical' => $canonical,
                'description' => $seo['description'],
                'styles' => ['/modules/FAQ/assets/public.css?v=20260309'],
                'jsonld' => $jsonLd,
                'image' => $seo['image'],
                'og' => [
                    'title' => $seo['og_title'],
                    'description' => $seo['og_description'],
                    'image' => $seo['image'],
                    'url' => $canonical,
                ],
                'twitter' => [
                    'title' => $seo['og_title'],
                    'description' => $seo['og_description'],
                    'image' => $seo['image'],
                ],
            ]
        );
        return new Response($html);
    }

    private function resolveSeo(Request $request): array
    {
        $locale = $this->container->get('lang')->current();
        $settings = array_merge([
            'seo_title_en' => 'Tattoo FAQ',
            'seo_title_ru' => 'FAQ о татуировках',
            'seo_desc_en' => 'Tattoo FAQ about booking, pain, healing, aftercare and safety.',
            'seo_desc_ru' => 'Частые вопросы о татуировках: запись, боль, заживление, уход и безопасность.',
            'og_title_en' => '',
            'og_title_ru' => '',
            'og_desc_en' => '',
            'og_desc_ru' => '',
            'og_image' => '',
        ], $this->moduleSettings->all('faq'));

        $title = $this->localizedValue($settings, 'seo_title', $locale);
        $description = $this->localizedValue($settings, 'seo_desc', $locale);
        $ogTitle = $this->localizedValue($settings, 'og_title', $locale) ?: $title;
        $ogDescription = $this->localizedValue($settings, 'og_desc', $locale) ?: $description;
        $image = trim((string) ($settings['og_image'] ?? ''));

        if ($image !== '' && !str_starts_with($image, 'http://') && !str_starts_with($image, 'https://') && str_starts_with($image, '/')) {
            $image = $this->baseUrl($request) . $image;
        }

        return [
            'title' => $title,
            'description' => $description,
            'og_title' => $ogTitle,
            'og_description' => $ogDescription,
            'image' => $image !== '' ? $image : null,
        ];
    }

    private function localizedValue(array $settings, string $baseKey, string $locale): string
    {
        $primary = trim((string) ($locale === 'ru' ? ($settings[$baseKey . '_ru'] ?? '') : ($settings[$baseKey . '_en'] ?? '')));
        if ($primary !== '') {
            return $primary;
        }

        return trim((string) ($locale === 'ru' ? ($settings[$baseKey . '_en'] ?? '') : ($settings[$baseKey . '_ru'] ?? '')));
    }

    private function normalizeItems(array $items): array
    {
        $normalized = [];

        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' || $answer === '') {
                continue;
            }

            $item['question'] = $question;
            $item['answer'] = $answer;
            $item['topic'] = $this->detectTopic($question . ' ' . $answer);
            $normalized[] = $item;
        }

        return $normalized;
    }

    private function buildTopicGroups(array $items): array
    {
        $definitions = [
            'all' => [
                'label' => 'All Questions',
                'description' => 'A complete tattoo FAQ for clients before and after the session.',
            ],
            'booking' => [
                'label' => 'Booking',
                'description' => 'How appointments, deposits and scheduling work.',
            ],
            'design' => [
                'label' => 'Design',
                'description' => 'Choosing style, size, placement and cover-up options.',
            ],
            'preparation' => [
                'label' => 'Preparation',
                'description' => 'What to do before your tattoo session.',
            ],
            'aftercare' => [
                'label' => 'Aftercare',
                'description' => 'Healing, washing, sun protection and long-term care.',
            ],
            'safety' => [
                'label' => 'Safety',
                'description' => 'Sterility, pain, allergies and contraindications.',
            ],
        ];

        $groups = [];
        foreach ($definitions as $slug => $definition) {
            $groups[$slug] = [
                'slug' => $slug,
                'label' => $definition['label'],
                'description' => $definition['description'],
                'items' => [],
            ];
        }

        $groups['all']['items'] = $items;

        foreach ($items as $item) {
            $topic = $item['topic'] ?? 'aftercare';
            if (!isset($groups[$topic])) {
                $topic = 'aftercare';
            }
            $groups[$topic]['items'][] = $item;
        }

        return array_values(array_filter($groups, static function (array $group): bool {
            return !empty($group['items']);
        }));
    }

    private function buildFaqSchema(array $items): array
    {
        $entities = [];
        foreach ($items as $item) {
            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));
            if ($question === '' || $answer === '') {
                continue;
            }

            $entities[] = [
                '@type' => 'Question',
                'name' => $question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $answer,
                ],
            ];
        }

        if ($entities === []) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $entities,
        ];
    }

    private function detectTopic(string $text): string
    {
        $normalized = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
        $map = [
            'booking' => ['запис', 'предоплат', 'депозит', 'сеанс', 'appointment', 'booking', 'price', 'стоим'],
            'design' => ['эскиз', 'стиль', 'placement', 'размер', 'перекры', 'cover-up', 'design'],
            'preparation' => ['подготов', 'перед сеансом', 'есть перед', 'sleep', 'брить', 'before session'],
            'aftercare' => ['зажив', 'уход', 'плёнк', 'пленк', 'wash', 'healing', 'sun', 'крем'],
            'safety' => ['аллерг', 'стериль', 'infection', 'безопас', 'противопоказ', 'pain', 'больно'],
        ];

        foreach ($map as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && mb_strpos($normalized, $keyword) !== false) {
                    return $topic;
                }
            }
        }

        return 'aftercare';
    }

    private function canonical(Request $request): string
    {
        return $this->baseUrl($request) . ($request->path ?? '/');
    }

    private function baseUrl(Request $request): string
    {
        $cfg = include APP_ROOT . '/app/config/app.php';
        $base = rtrim($cfg['url'] ?? '', '/');
        if (!$base) {
            $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $request->server['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host;
        }
        return $base;
    }
}
