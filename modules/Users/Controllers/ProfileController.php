<?php
namespace Modules\Users\Controllers;

use App\Services\UserPublicSummaryService;
use Core\Container;
use Core\Csrf;
use Core\Meta\CommonSchemas;
use Core\Meta\JsonLdRenderer;
use Core\ModuleSettings;
use Core\Request;
use Core\Response;
use Modules\Users\Services\Auth;
use Modules\Users\Services\AvatarService;
use Modules\Users\Services\CollectionService;
use Modules\Users\Services\CommunityPollService;
use Modules\Users\Services\ProfileCoverService;
use Modules\Users\Services\PhotoCopyrightService;
use Modules\Users\Services\UserAccessService;
use Modules\Users\Services\MasterContactService;
use Modules\Users\Services\UserRepository;

class ProfileController
{
    private Container $container;
    private Auth $auth;
    private UserRepository $users;
    private AvatarService $avatars;
    private ProfileCoverService $covers;
    private ModuleSettings $moduleSettings;
    private UserAccessService $access;
    private CollectionService $collections;
    private CommunityPollService $communityPolls;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->auth = $container->get(Auth::class);
        $this->users = $container->get(UserRepository::class);
        $this->avatars = $container->get(AvatarService::class);
        $this->covers = $container->get(ProfileCoverService::class);
        $this->moduleSettings = $container->get(ModuleSettings::class);
        $this->access = $container->get(UserAccessService::class);
        $this->collections = $container->get(CollectionService::class);
        $this->communityPolls = $container->get(CommunityPollService::class);
    }

    public function show(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $user = $this->users->findFull((int)$user['id']) ?? $user;
        $activeTab = $this->normalizeProfileTab((string)($request->query['tab'] ?? 'overview'));
        $collectionsAvailable = $this->collections->available();
        $collections = $collectionsAvailable ? $this->collections->listForUser((int)$user['id']) : [];
        $requestedCollectionId = max(0, (int)($request->query['collection'] ?? 0));
        $currentCollection = null;
        if ($collectionsAvailable) {
            $currentCollection = $requestedCollectionId > 0
                ? $this->collections->findOwned((int)$user['id'], $requestedCollectionId)
                : ($collections[0] ?? null);
        }
        $collectionItems = $currentCollection
            ? $this->collections->itemsForCollection((int)$user['id'], (int)$currentCollection['id'])
            : [];
        $html = $this->container->get('renderer')->render('users/profile', [
            '_layout' => true,
            'title' => 'Profile',
            'user' => $user,
            'csrf' => Csrf::token('profile_update'),
            'avatarToken' => Csrf::token('profile_avatar'),
            'coverToken' => Csrf::token('profile_cover'),
            'favoriteToken' => Csrf::token('users_favorites'),
            'collectionToken' => Csrf::token('users_collections'),
            'message' => $request->query['msg'] ?? null,
            'error' => $request->query['err'] ?? null,
            'visibilityOptions' => ['public', 'private'],
            'logoutToken' => Csrf::token('logout'),
            'favorites' => $this->users->favoritesForUser((int)$user['id']),
            'myComments' => $this->users->commentsForUser((int)$user['id']),
            'works' => $this->users->recentWorksForUser((int)$user['id']),
            'groups' => $this->access->groupsForUser((int)$user['id']),
            'ratings' => $this->users->ratingsSummaryForUser((int)$user['id']),
            'usersSettings' => $this->settings(),
            'canManageMasterWorks' => $this->canManageMasterWorks($user),
            'canManagePhotoCopyright' => $this->canManagePhotoCopyright($user),
            'verifiedMasterFaqItems' => $this->canManagePhotoCopyright($user) ? $this->verifiedMasterFaqItems() : [],
            'photoCopyrightFonts' => PhotoCopyrightService::fontOptions(),
            'photoCopyrightDefaultColor' => PhotoCopyrightService::defaultColor(),
            'activeTab' => $activeTab,
            'collectionsAvailable' => $collectionsAvailable,
            'collections' => $collections,
            'currentCollection' => $currentCollection,
            'collectionItems' => $collectionItems,
            'communityPoll' => $this->communityPolls->surveyForUser((int)$user['id']),
            'communityPollToken' => Csrf::token('community_poll_submit'),
        ], [
            'title' => 'Profile',
        ]);
        return new Response($html);
    }

    public function update(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_update', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $name = trim($request->body['name'] ?? '');
        $email = strtolower(trim($request->body['email'] ?? ''));
        $pass = (string)($request->body['password'] ?? '');
        $pass2 = (string)($request->body['password_confirm'] ?? '');
        $visibility = $this->normalizeVisibility((string)($request->body['profile_visibility'] ?? 'public'));
        if ($this->settings()['forbid_raw_external_links'] && $this->hasRawLinksInProfileText($request->body, !empty($user['is_master']))) {
            return $this->redirectWithError((string)__('users.profile.error.raw_links'));
        }
        $signature = $this->sanitizeSignature($request->body['signature'] ?? null);
        if ($name === '' || $email === '') {
            return $this->redirectWithError('Name and email required');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWithError('Invalid email');
        }
        if ($this->users->emailExists($email, (int)$user['id'])) {
            return $this->redirectWithError('Email already used');
        }
        $data = [
            'name' => $name,
            'email' => $email,
            'username' => $user['username'] ?? null,
            'profile_visibility' => $visibility,
            'signature' => $signature,
        ];
        $profileData = [
            'display_name' => $this->sanitizeShortText($request->body['display_name'] ?? null, 160),
            'bio' => $this->sanitizeLongText($request->body['bio'] ?? null, 2000),
            'artist_note' => !empty($user['is_master']) ? $this->sanitizeShortText($request->body['artist_note'] ?? null, 280) : null,
            'specialization' => $this->sanitizeShortText($request->body['specialization'] ?? null, 255),
            'styles' => $this->sanitizeShortText($request->body['styles'] ?? null, 255),
            'city' => $this->sanitizeShortText($request->body['city'] ?? null, 120),
            'studio_name' => $this->sanitizeShortText($request->body['studio_name'] ?? null, 160),
            'experience_years' => max(0, min(80, (int)($request->body['experience_years'] ?? 0))),
            'price_from' => $this->normalizePrice($request->body['price_from'] ?? null),
            'booking_status' => $this->normalizeBookingStatus((string)($request->body['booking_status'] ?? 'open')),
            'contacts_text' => $this->sanitizeLongText($request->body['contacts_text'] ?? null, 1000),
            'external_links_json' => $this->normalizeSocialLinks($request->body, $user),
            'cover_image' => $this->sanitizeCoverImage($request->body['cover_image'] ?? null),
            'visibility_mode' => $visibility,
            'show_contacts' => !empty($request->body['show_contacts']) ? 1 : 0,
            'show_favorites' => !empty($request->body['show_favorites']) ? 1 : 0,
            'show_comments' => !empty($request->body['show_comments']) ? 1 : 0,
            'show_ratings' => !empty($request->body['show_ratings']) ? 1 : 0,
            'show_works' => !empty($request->body['show_works']) ? 1 : 0,
            'show_personal_feed' => !empty($request->body['show_personal_feed']) ? 1 : 0,
            'show_personal_feed_works' => !empty($request->body['show_personal_feed_works']) ? 1 : 0,
            'show_personal_feed_masters' => !empty($request->body['show_personal_feed_masters']) ? 1 : 0,
            'comments_moderation' => !empty($request->body['comments_moderation']) ? 1 : 0,
            'hide_online_status' => !empty($request->body['hide_online_status']) ? 1 : 0,
            'is_master' => $this->access->can($user, 'gallery.submit') ? (!empty($request->body['is_master']) ? 1 : 0) : (!empty($user['is_master']) ? 1 : 0),
        ];
        if ($this->canManagePhotoCopyright($user)) {
            $profileData['photo_copyright_enabled'] = !empty($request->body['photo_copyright_enabled']) ? 1 : 0;
            $profileData['photo_copyright_text'] = $this->sanitizeShortText($request->body['photo_copyright_text'] ?? null, 120);
            $profileData['photo_copyright_font'] = $this->normalizePhotoCopyrightFont((string)($request->body['photo_copyright_font'] ?? 'oswald'));
            $profileData['photo_copyright_color'] = $this->normalizePhotoCopyrightColor((string)($request->body['photo_copyright_color'] ?? PhotoCopyrightService::defaultColor()));
        }
        if ($pass !== '') {
            if ($pass !== $pass2) {
                return $this->redirectWithError('Passwords do not match');
            }
            if (strlen($pass) < 8) {
                return $this->redirectWithError('Password must be at least 8 characters');
            }
            $this->users->setPassword((int)$user['id'], password_hash($pass, PASSWORD_DEFAULT));
        }
        $this->users->update((int)$user['id'], $data);
        $this->users->upsertProfile((int)$user['id'], $profileData);
        $this->container->get(UserPublicSummaryService::class)->invalidate((int)$user['id']);
        return new Response('', 302, ['Location' => '/profile?tab=settings&msg=updated']);
    }

    public function avatar(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_avatar', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $saved = (new \Modules\Users\Services\AvatarProcessor())->processUploadedImage($request->files['avatar']['tmp_name'] ?? '', (int)$user['id']);
        if (!$saved) {
            return $this->redirectWithError('Avatar upload failed');
        }
        $this->users->update((int)$user['id'], ['avatar' => $saved]);
        $this->container->get(UserPublicSummaryService::class)->invalidate((int)$user['id']);
        return new Response('', 302, ['Location' => '/profile?msg=avatar']);
    }

    public function avatarEditor(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        $html = $this->container->get('renderer')->render('users/avatar_editor', [
            '_layout' => true,
            'title' => 'Edit avatar',
            'csrf' => Csrf::token('profile_avatar_crop'),
            'user' => $user,
        ], [
            'title' => 'Edit avatar',
        ]);
        return new Response($html);
    }

    public function avatarCrop(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_avatar_crop', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }
        $tmp = $request->files['avatar']['tmp_name'] ?? '';
        $cropX = (int)($request->body['crop_x'] ?? 0);
        $cropY = (int)($request->body['crop_y'] ?? 0);
        $cropW = (int)($request->body['crop_w'] ?? 0);
        $cropH = (int)($request->body['crop_h'] ?? 0);
        $scale = (float)($request->body['crop_scale'] ?? 1.0);
        $processor = new \Modules\Users\Services\AvatarProcessor();
        $saved = $processor->processWithCrop($tmp, (int)$user['id'], $cropX, $cropY, $cropW, $cropH, $scale);
        if (!$saved) {
            return $this->redirectWithError('Avatar crop failed');
        }
        $this->users->update((int)$user['id'], ['avatar' => $saved]);
        $this->container->get(UserPublicSummaryService::class)->invalidate((int)$user['id']);
        return new Response('', 302, ['Location' => '/profile?msg=avatar']);
    }

    public function cover(Request $request): Response
    {
        $user = $this->auth->user();
        if (!$user) {
            return new Response('', 302, ['Location' => '/login']);
        }
        if (!Csrf::check('profile_cover', $request->body['_token'] ?? null)) {
            return new Response('Invalid CSRF token', 400);
        }

        $saved = $this->covers->processUploadedImage((string)($request->files['cover']['tmp_name'] ?? ''), (int)$user['id']);
        if ($saved === '') {
            return $this->redirectWithError('Cover upload failed');
        }

        $this->users->upsertProfile((int)$user['id'], ['cover_image' => $saved]);

        return new Response('', 302, ['Location' => '/profile?tab=settings&msg=cover']);
    }

    public function publicProfile(Request $request): Response
    {
        $identifier = trim((string)($request->params['id'] ?? ''));
        $user = $this->findByIdentifier($identifier);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return new Response('Not found', 404);
        }
        $viewer = $this->auth->user();
        $isOwner = $viewer && (int)$viewer['id'] === (int)$user['id'];
        $isAdmin = $this->auth->checkRole('admin');
        $isPrivate = ($user['profile_visibility'] ?? 'public') === 'private';
        $restricted = $isPrivate && !$isOwner && !$isAdmin;
        $canonical = $this->profileUrl($request, $user);
        $meta = [
            'title' => $restricted ? 'Profile is private' : ($user['name'] ?? 'User'),
            'canonical' => $canonical,
        ];
        if ($restricted) {
            $meta['robots'] = 'noindex,nofollow';
        }
        $meta['description'] = $this->profileDescription($user, $restricted);
        $meta['jsonld'] = $restricted ? '' : $this->profileJsonLd($request, $user);
        $meta['og'] = $this->profileOpenGraph($request, $user, $restricted, $canonical, $meta['description']);
        $meta['twitter'] = [
            'card' => !empty($meta['og']['image']) ? 'summary_large_image' : 'summary',
            'title' => $meta['og']['title'] ?? $meta['title'],
            'description' => $meta['og']['description'] ?? $meta['description'],
            'image' => $meta['og']['image'] ?? null,
        ];
        $commentsHtml = '';
        if (!$restricted && !empty($user['show_comments']) && !empty($this->settings()['profile_comments_enabled'])) {
            try {
                $commentsHtml = $this->container->get(\Modules\Comments\Services\CommentService::class)
                    ->renderForEntity('user_profile', (int)$user['id'], ['title' => 'Profile comments']);
            } catch (\Throwable $e) {
                $commentsHtml = '';
            }
        }
        $viewerFavorite = false;
        if ($viewer) {
            $viewerFavorite = $this->users->isFavorite((int)$viewer['id'], 'user_profile', (int)$user['id']);
        }
        $ratings = $this->users->ratingsSummaryForUser((int)$user['id']);
        $html = $this->container->get('renderer')->render(
            'users/public_profile',
            [
                '_layout' => true,
                'title' => $meta['title'],
                'user' => $user,
                'restricted' => $restricted,
                'username' => $user['username'] ?? '',
                'canViewDetails' => !$restricted,
                'favoriteToken' => Csrf::token('users_favorites'),
                'collectionToken' => Csrf::token('users_collections'),
                'isOwner' => $isOwner,
                'viewer' => $viewer,
                'isFavorite' => $viewerFavorite,
                'favorites' => !empty($user['show_favorites']) ? $this->users->favoritesForUser((int)$user['id'], 6) : [],
                'works' => !empty($user['show_works']) ? $this->users->recentWorksForUser((int)$user['id'], 6) : [],
                'commentsHtml' => $commentsHtml,
                'ratings' => $ratings,
                'usersSettings' => $this->settings(),
                'masterContactAvailability' => $this->masterContactAvailability($user),
                'collectionsAvailable' => $this->collections->available(),
                'message' => $request->query['msg'] ?? null,
                'error' => $request->query['err'] ?? null,
                'breadcrumbs' => [
                    ['label' => 'Users', 'url' => '/users'],
                    ['label' => $user['display_name'] ?? ($user['name'] ?? 'User')],
                ],
            ],
            $meta
        );
        return new Response($html);
    }

    public function publicIndex(Request $request): Response
    {
        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 18;
        $total = $this->users->publicDirectoryCount();
        $pages = max(1, (int)ceil($total / $perPage));
        if ($page > $pages) {
            $page = $pages;
        }

        $items = $this->users->publicDirectory($perPage, ($page - 1) * $perPage);
        $title = (string)__('users.directory.title');
        $canonical = $this->baseUrl($request) . '/users' . ($page > 1 ? ('?page=' . $page) : '');

        $html = $this->container->get('renderer')->render(
            'users/public_index',
            [
                '_layout' => true,
                'title' => $title,
                'users' => $items,
                'page' => $page,
                'pages' => $pages,
                'totalUsers' => $total,
                'breadcrumbs' => [
                    ['label' => 'Users'],
                ],
            ],
            [
                'title' => $title,
                'description' => (string)__('users.directory.subtitle'),
                'canonical' => $canonical,
            ]
        );

        return new Response($html);
    }

    public function publicWorks(Request $request): Response
    {
        $identifier = trim((string)($request->params['id'] ?? ''));
        $user = $this->findByIdentifier($identifier);
        if (!$user || ($user['status'] ?? '') !== 'active') {
            return new Response('Not found', 404);
        }

        $viewer = $this->auth->user();
        $isOwner = $viewer && (int)$viewer['id'] === (int)$user['id'];
        $isAdmin = $this->auth->checkRole('admin');
        $isPrivate = ($user['profile_visibility'] ?? 'public') === 'private';
        $restricted = $isPrivate && !$isOwner && !$isAdmin;

        if ($restricted || empty($user['show_works'])) {
            return new Response('Not found', 404);
        }

        $page = max(1, (int)($request->query['page'] ?? 1));
        $perPage = 24;
        $total = $this->users->publicWorksCountForUser((int)$user['id']);
        $works = $this->users->publicWorksForUser((int)$user['id'], $perPage, ($page - 1) * $perPage);
        $pages = max(1, (int)ceil($total / $perPage));
        $title = (string)(($user['display_name'] ?? $user['name'] ?? 'User') . ' works');

        $html = $this->container->get('renderer')->render(
            'users/public_works',
            [
                '_layout' => true,
                'title' => $title,
                'user' => $user,
                'works' => $works,
                'page' => $page,
                'pages' => $pages,
                'totalWorks' => $total,
                'profileUrl' => '/users/' . rawurlencode((string)($user['username'] ?? $user['id'])),
                'baseUrl' => '/users/' . rawurlencode((string)($user['username'] ?? $user['id'])) . '/works',
                'breadcrumbs' => [
                    ['label' => 'Users', 'url' => '/users'],
                    ['label' => $user['display_name'] ?? ($user['name'] ?? 'User'), 'url' => '/users/' . rawurlencode((string)($user['username'] ?? $user['id']))],
                    ['label' => 'Works'],
                ],
            ],
            [
                'title' => $title,
                'description' => 'Gallery works by ' . (string)($user['display_name'] ?? $user['name'] ?? 'User'),
                'canonical' => $this->profileUrl($request, $user) . '/works' . ($page > 1 ? ('?page=' . $page) : ''),
            ]
        );

        return new Response($html);
    }

    private function redirectWithError(string $msg): Response
    {
        return new Response('', 302, ['Location' => '/profile?tab=settings&err=' . urlencode($msg)]);
    }

    private function normalizeProfileTab(string $value): string
    {
        $value = strtolower(trim($value));
        $allowed = ['overview', 'settings', 'activity', 'collections', 'community'];
        return in_array($value, $allowed, true) ? $value : 'overview';
    }

    private function verifiedMasterFaqItems(): array
    {
        return [
            [
                'question' => 'Что даёт подтверждённый аккаунт мастера',
                'answer' => [
                    'Подтверждённый профиль нужен для публичного доверия и расширенных инструментов внутри кабинета. Такой аккаунт может оформлять мастер-профиль, показывать портфолио, загружать работы в галерею и использовать защиту фото с копирайтом.',
                    'Если у вас открыт мастерский профиль, следите за качеством публичной карточки: display name, город, стили, обложка, аватар, описание и контакты напрямую влияют на то, как профиль смотрится в каталоге и витрине Top Tattoo Masters.',
                ],
            ],
            [
                'question' => 'Как правильно заполнить профиль, чтобы он выглядел сильно',
                'answer' => [
                    'Сначала заполните базу: отображаемое имя, город, студию, специализацию и список стилей. Не оставляйте профиль полу-пустым: краткая, конкретная информация работает лучше, чем общий текст без фактов.',
                    'В bio лучше писать не «делаю любые тату», а чётко обозначать сильные стороны: например, blackwork, realism, fine line, cover-up, custom projects. Отдельно полезно указать опыт, минимальный чек и актуальный статус записи.',
                    'Обложка профиля должна быть широкой и качественной. Аватар лучше брать чистый, контрастный, без мелкого шума. Если есть узнаваемый визуальный стиль, выдерживайте его и в профиле, и в галерее.',
                ],
            ],
            [
                'question' => 'Как работает публикация работ в галерею',
                'answer' => [
                    'Загрузка идёт через раздел работ в кабинете. Для витрины и каталога важны именно аккуратно оформленные approved-работы: с нормальным заголовком, чистым изображением и понятным стилем.',
                    'Если на сайте включена модерация или отдельные правила для verified-мастеров, новая работа может сначала попасть в pending. Это нормальный этап. После одобрения она участвует в публичной галерее и начинает влиять на видимость профиля.',
                    'Не загружайте в портфолио дубликаты, пересжатые скрины, коллажи с интерфейсом телефона или кадры с грязным светом. Галерея работает как репутационный слой, а не как черновик.',
                ],
            ],
            [
                'question' => 'Как влияет рейтинг Top Tattoo Masters',
                'answer' => [
                    'Рейтинг не строится по одному параметру. На позиции влияет заполненность профиля, наличие approved работ, оценки, число добавлений в избранное и лайки работ из галереи.',
                    'Это означает, что пустой профиль даже с verified-статусом не будет выглядеть сильным. И наоборот, аккуратный мастерский профиль с хорошими работами и живой реакцией аудитории получает заметно больше шансов на видимость.',
                    'Если хотите усиливать позиции, обновляйте портфолио регулярно, держите обложку и описание в порядке и не забывайте про публичную часть профиля.',
                ],
            ],
            [
                'question' => 'Чем отличаются обычные лайки, признание мастеров и расчёт ранга',
                'answer' => [
                    'На сайте теперь есть два разных сигнала для работ в Gallery. Обычные лайки показывают реакцию аудитории. Признание мастеров, или master likes, это отдельная профессиональная отметка от подтверждённых мастеров и она не смешивается с обычными лайками.',
                    'Для Top Tattoo Masters эти сигналы тоже считаются отдельно. В итоговом ranking score самый сильный вес получает именно профессиональное признание approved работ, привязанных к вашему профилю. Обычные лайки, избранное, рейтинг, количество работ и заполненность профиля тоже влияют, но слабее.',
                    'Практически это значит простую вещь: verified badge сам по себе не поднимает профиль в топ автоматически. Сильнее всего работает связка из чистого профиля, хороших approved работ в галерее и живого признания со стороны других подтверждённых мастеров.',
                ],
            ],
            [
                'question' => 'Зачем нужен копирайт на фото и как им пользоваться',
                'answer' => [
                    'Watermark нужен не для того, чтобы испортить кадр, а чтобы защитить авторство при репостах и воровстве изображений. Лучше использовать короткий и читаемый знак: @nickname, @studio или компактный бренд.',
                    'Шрифт и цвет выбирайте под реальные фото. Если кадры в основном тёмные, берите светлый цвет. Если фото светлые и минималистичные, используйте более плотный тёмный оттенок. Слишком агрессивный watermark может убить качество портфолио.',
                    'Проверьте превью перед сохранением. Watermark применяется к новым загрузкам, поэтому настройте его один раз аккуратно, а не меняйте стиль хаотично перед каждой работой.',
                ],
            ],
            [
                'question' => 'Как лучше оформить портфолио для клиентов',
                'answer' => [
                    'Портфолио должно показывать не количество файлов, а уровень. Лучше меньше, но сильнее: несколько чистых, уверенных работ по вашему стилю смотрятся лучше, чем десятки случайных изображений.',
                    'Старайтесь держать единый уровень света и кадрирования. Добавляйте только те работы, которые вам не стыдно поставить на первый экран профиля или в топ-каталог.',
                    'Если работаете в нескольких стилях, не смешивайте всё без структуры. Укажите стили в профиле и подбирайте портфолио так, чтобы посетителю было понятно, в чём именно ваша специализация.',
                ],
            ],
            [
                'question' => 'Какие контакты и ссылки лучше показывать публично',
                'answer' => [
                    'Публично стоит показывать только рабочие каналы: Telegram, Instagram, WhatsApp, VK, YouTube, TikTok и при необходимости сайт студии. Не захламляйте профиль ссылками, которыми не пользуетесь.',
                    'Контакты должны вести в живые, понятные и актуальные точки связи. Если у клиента путь записи идёт через один основной мессенджер, не распыляйте внимание на пять мёртвых ссылок.',
                    'Периодически проверяйте, что все ссылки открываются корректно. Битые или пустые контакты снижают доверие быстрее, чем кажется.',
                ],
            ],
            [
                'question' => 'Как вести публичный профиль стабильно, а не разово',
                'answer' => [
                    'Хороший профиль не собирается один раз навсегда. Его нужно поддерживать: обновлять обложку, подчищать bio, заменять слабые работы, добавлять сильные свежие проекты и следить за актуальностью статуса записи.',
                    'Если у вас меняется город, студия, специализация или формат приёма клиентов, обновляйте это сразу. Иначе профиль начинает жить отдельно от реального мастера.',
                    'Думайте о странице как о рабочей витрине: клиенту должно быть понятно, кто вы, что делаете, в каком стиле сильны и как с вами связаться прямо сейчас.',
                ],
            ],
        ];
    }

    private function normalizeUsername(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/\\s+/', '-', $value);
        $value = preg_replace('/[^a-z0-9_.\\-]+/', '', $value);
        $value = trim($value, '-_.');
        $max = $this->usernameMax();
        if ($max > 0 && strlen($value) > $max) {
            $value = substr($value, 0, $max);
        }
        if (ctype_digit($value)) {
            $value = 'u' . $value;
        }
        return $value;
    }

    private function normalizeVisibility(string $raw): string
    {
        return $raw === 'private' ? 'private' : 'public';
    }

    private function sanitizeSignature($value): ?string
    {
        $plain = trim((string)$value);
        if ($plain === '') {
            return null;
        }
        $plain = strip_tags($plain);
        $plain = preg_replace('~(?:https?://|www\.)\S+~iu', ' ', $plain);
        $plain = preg_replace('~(?<!@)\b[\p{L}\p{N}][\p{L}\p{N}\-._]*\.[a-z]{2,}(?:/[^\s]*)?~iu', ' ', $plain);
        $plain = preg_replace('/\\s+/', ' ', $plain);
        $plain = trim($plain);
        if ($plain === '') {
            return null;
        }
        if (mb_strlen($plain) > 300) {
            $plain = mb_substr($plain, 0, 300);
        }
        return $plain;
    }

    private function hasRawLinksInProfileText(array $body, bool $isMaster): bool
    {
        $fields = [
            'signature',
            'display_name',
            'bio',
            'specialization',
            'styles',
            'city',
            'studio_name',
            'contacts_text',
            'photo_copyright_text',
        ];
        if ($isMaster) {
            $fields[] = 'artist_note';
        }

        foreach ($fields as $field) {
            if ($this->containsRawLink((string)($body[$field] ?? ''))) {
                return true;
            }
        }

        return false;
    }

    private function containsRawLink(string $value): bool
    {
        $value = trim(strip_tags($value));
        if ($value === '') {
            return false;
        }

        return (bool)preg_match(
            '~(?:https?://|www\.)\S+|(?<!@)\b[\p{L}\p{N}][\p{L}\p{N}\-._]*\.[a-z]{2,}(?:/[^\s]*)?~iu',
            $value
        );
    }

    private function usernameMin(): int
    {
        $min = (int)($this->moduleSettings->all('users')['username_min_length'] ?? 3);
        return $min > 0 ? $min : 3;
    }

    private function usernameMax(): int
    {
        $settings = $this->moduleSettings->all('users');
        $min = $this->usernameMin();
        $max = (int)($settings['username_max_length'] ?? 32);
        if ($max < $min) {
            $max = $min;
        }
        return $max;
    }

    private function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }
        if (ctype_digit($identifier)) {
            $byId = $this->users->findFull((int)$identifier);
            if ($byId) {
                return $byId;
            }
        }
        return $this->users->findFullByUsername($identifier);
    }

    private function sanitizeShortText($value, int $max): ?string
    {
        $plain = trim(strip_tags((string)$value));
        if ($plain === '') {
            return null;
        }
        $plain = preg_replace('/\s+/', ' ', $plain);
        return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max) : $plain;
    }

    private function sanitizeLongText($value, int $max): ?string
    {
        $plain = trim(strip_tags((string)$value));
        if ($plain === '') {
            return null;
        }
        return mb_strlen($plain) > $max ? mb_substr($plain, 0, $max) : $plain;
    }

    private function normalizeBookingStatus(string $raw): string
    {
        $allowed = ['open', 'busy', 'closed'];
        $raw = strtolower(trim($raw));
        return in_array($raw, $allowed, true) ? $raw : 'open';
    }

    private function normalizePrice($value): ?string
    {
        $raw = trim((string)$value);
        if ($raw === '') {
            return null;
        }
        $raw = str_replace(',', '.', $raw);
        if (!is_numeric($raw)) {
            return null;
        }
        return number_format((float)$raw, 2, '.', '');
    }

    private function normalizeSocialLinks(array $body, array $user): ?string
    {
        $allowedPlatforms = $this->allowedSocialPlatforms();
        $links = [];

        foreach ($allowedPlatforms as $platform) {
            $value = trim((string)($body['social_' . $platform] ?? ''));
            if ($value === '') {
                continue;
            }
            $normalized = $this->normalizeSocialLink($platform, $value, $user);
            if ($normalized !== null) {
                $links[$platform] = $normalized;
            }
        }

        return $links ? json_encode($links, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
    }

    private function sanitizeCoverImage($value): ?string
    {
        $url = trim((string)$value);
        if ($url === '') {
            return null;
        }
        if (str_starts_with($url, '/')) {
            return $url;
        }
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }
        return null;
    }

    private function settings(): array
    {
        $settings = $this->moduleSettings->all('users');
        return [
            'extended_profiles_enabled' => array_key_exists('extended_profiles_enabled', $settings) ? !empty($settings['extended_profiles_enabled']) : true,
            'groups_enabled' => array_key_exists('groups_enabled', $settings) ? !empty($settings['groups_enabled']) : true,
            'master_profiles_enabled' => array_key_exists('master_profiles_enabled', $settings) ? !empty($settings['master_profiles_enabled']) : true,
            'favorites_enabled' => array_key_exists('favorites_enabled', $settings) ? !empty($settings['favorites_enabled']) : true,
            'ratings_enabled' => array_key_exists('ratings_enabled', $settings) ? !empty($settings['ratings_enabled']) : true,
            'reviews_enabled' => array_key_exists('reviews_enabled', $settings) ? !empty($settings['reviews_enabled']) : true,
            'profile_comments_enabled' => array_key_exists('profile_comments_enabled', $settings) ? !empty($settings['profile_comments_enabled']) : true,
            'cover_enabled' => array_key_exists('cover_enabled', $settings) ? !empty($settings['cover_enabled']) : true,
            'external_links_enabled' => array_key_exists('external_links_enabled', $settings) ? !empty($settings['external_links_enabled']) : true,
            'contacts_enabled' => array_key_exists('contacts_enabled', $settings) ? !empty($settings['contacts_enabled']) : true,
            'master_uploads_enabled' => array_key_exists('master_uploads_enabled', $settings) ? !empty($settings['master_uploads_enabled']) : true,
            'verified_masters_only_upload' => array_key_exists('verified_masters_only_upload', $settings) ? !empty($settings['verified_masters_only_upload']) : true,
            'master_gallery_moderation' => array_key_exists('master_gallery_moderation', $settings) ? !empty($settings['master_gallery_moderation']) : true,
            'master_plans_enabled' => array_key_exists('master_plans_enabled', $settings) ? !empty($settings['master_plans_enabled']) : true,
            'allowed_social_platforms' => trim((string)($settings['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp')),
            'forbid_raw_external_links' => array_key_exists('forbid_raw_external_links', $settings) ? !empty($settings['forbid_raw_external_links']) : true,
            'username_change_disabled' => array_key_exists('username_change_disabled', $settings) ? !empty($settings['username_change_disabled']) : true,
        ];
    }

    private function canManageMasterWorks(array $user): bool
    {
        $settings = $this->settings();
        if (empty($settings['master_uploads_enabled'])) {
            return false;
        }
        if (empty($user['is_master'])) {
            return false;
        }
        if (!empty($settings['verified_masters_only_upload']) && empty($user['is_verified'])) {
            return false;
        }
        return true;
    }

    private function canManagePhotoCopyright(array $user): bool
    {
        return !empty($user['is_verified']) && $this->canManageMasterWorks($user);
    }

    private function normalizePhotoCopyrightFont(string $value): string
    {
        $value = strtolower(trim($value));
        return array_key_exists($value, PhotoCopyrightService::fontOptions()) ? $value : 'oswald';
    }

    private function normalizePhotoCopyrightColor(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^#?[0-9a-fA-F]{6}$/', $value)) {
            return PhotoCopyrightService::defaultColor();
        }
        return '#' . strtolower(ltrim($value, '#'));
    }

    private function allowedSocialPlatforms(): array
    {
        $raw = $this->settings()['allowed_social_platforms'] ?? 'telegram,vk,instagram,youtube,tiktok,whatsapp';
        $platforms = array_values(array_unique(array_filter(array_map(static function (string $value): string {
            return strtolower(trim($value));
        }, explode(',', (string)$raw)))));
        $supported = ['telegram', 'vk', 'instagram', 'youtube', 'tiktok', 'whatsapp'];
        return array_values(array_intersect($supported, $platforms));
    }

    private function normalizeSocialLink(string $platform, string $value, array $user): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $patterns = [
            'telegram' => '#^https://(t\.me|telegram\.me)/[A-Za-z0-9_]{3,}$#i',
            'vk' => '#^https://(www\.)?vk\.com/[A-Za-z0-9_.-]+$#i',
            'instagram' => '#^https://(www\.)?instagram\.com/[A-Za-z0-9_.-]+/?$#i',
            'youtube' => '#^https://(www\.)?(youtube\.com|youtu\.be)/.+$#i',
            'tiktok' => '#^https://(www\.)?tiktok\.com/@[A-Za-z0-9_.-]+/?$#i',
            'whatsapp' => '#^https://wa\.me/[0-9]{6,}$#i',
        ];

        if (!isset($patterns[$platform]) || !preg_match($patterns[$platform], $value)) {
            return null;
        }

        return $value;
    }

    private function profileDescription(array $user, bool $restricted): string
    {
        if ($restricted) {
            return 'Private user profile.';
        }

        $parts = [];
        if (!empty($user['specialization'])) {
            $parts[] = (string)$user['specialization'];
        }
        if (!empty($user['styles'])) {
            $parts[] = (string)$user['styles'];
        }
        if (!empty($user['city'])) {
            $parts[] = (string)$user['city'];
        }
        if (!empty($user['bio'])) {
            $parts[] = mb_substr((string)$user['bio'], 0, 160);
        }

        $description = trim(implode(' · ', array_filter($parts)));
        if ($description === '') {
            $name = (string)($user['display_name'] ?? $user['name'] ?? 'User');
            $description = $name . ' profile on TattooToday.';
        }

        return mb_strlen($description) > 180 ? mb_substr($description, 0, 180) : $description;
    }

    private function profileJsonLd(Request $request, array $user): string
    {
        $url = $this->profileUrl($request, $user);
        $sameAs = array_values($this->publicSocialLinks($user));
        $ratings = $this->users->ratingsSummaryForUser((int)($user['id'] ?? 0));
        $workCount = count($this->users->recentWorksForUser((int)($user['id'] ?? 0), 100));

        $person = CommonSchemas::person([
            'name' => (string)($user['display_name'] ?: ($user['name'] ?? 'User')),
            'url' => $url,
            'alternateName' => !empty($user['username']) ? '@' . $user['username'] : null,
            'description' => $this->profileDescription($user, false),
            'image' => $this->absoluteAssetUrl($request, (string)($user['avatar'] ?? '')),
            'jobTitle' => $user['specialization'] ?? null,
            'address' => !empty($user['city']) ? [
                '@type' => 'PostalAddress',
                'addressLocality' => $user['city'],
            ] : null,
            'worksFor' => !empty($user['studio_name']) ? [
                '@type' => 'Organization',
                'name' => $user['studio_name'],
            ] : null,
            'sameAs' => $sameAs ?: null,
            'knowsAbout' => !empty($user['styles']) ? array_values(array_filter(array_map('trim', explode(',', (string)$user['styles'])))) : null,
            'hasOccupation' => !empty($user['is_master']) ? [
                '@type' => 'Occupation',
                'name' => $user['specialization'] ?: 'Tattoo artist',
            ] : null,
            'award' => !empty($user['is_verified']) ? ['Verified profile'] : null,
            'interactionStatistic' => $workCount > 0 ? [[
                '@type' => 'InteractionCounter',
                'interactionType' => 'https://schema.org/WriteAction',
                'userInteractionCount' => $workCount,
            ]] : null,
            'aggregateRating' => !empty($ratings['count']) ? [
                '@type' => 'AggregateRating',
                'ratingValue' => $ratings['avg'] ?? 0,
                'reviewCount' => $ratings['count'],
                'bestRating' => 5,
                'worstRating' => 1,
            ] : null,
        ]);

        $profilePage = CommonSchemas::profilePage([
            'name' => (string)($user['display_name'] ?: ($user['name'] ?? 'User')),
            'url' => $url,
            'description' => $this->profileDescription($user, false),
            'mainEntity' => $person,
        ]);

        $breadcrumbs = CommonSchemas::breadcrumbList([
            ['name' => 'Home', 'url' => $this->baseUrl($request) . '/'],
            ['name' => 'Users', 'url' => $this->baseUrl($request) . '/users'],
            ['name' => (string)($user['display_name'] ?: ($user['name'] ?? 'User')), 'url' => $url],
        ]);

        return JsonLdRenderer::render(JsonLdRenderer::merge($profilePage, $breadcrumbs));
    }

    private function publicSocialLinks(array $user): array
    {
        if (empty($this->settings()['external_links_enabled']) || empty($user['show_contacts'])) {
            return [];
        }

        $links = json_decode((string)($user['external_links_json'] ?? ''), true);
        if (!is_array($links)) {
            return [];
        }

        $publicLinks = [];
        foreach ($this->allowedSocialPlatforms() as $platform) {
            $value = $links[$platform] ?? null;
            if (!is_string($value)) {
                continue;
            }

            $normalized = $this->normalizeSocialLink($platform, $value, $user);
            if ($normalized !== null) {
                $publicLinks[$platform] = $normalized;
            }
        }

        return $publicLinks;
    }

    private function profileUrl(Request $request, array $user): string
    {
        $base = $this->baseUrl($request);
        $identifier = (string)($user['username'] ?? $user['id'] ?? '');
        return $base . '/users/' . rawurlencode($identifier);
    }

    private function absoluteAssetUrl(Request $request, string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }
        if (str_starts_with($value, '/')) {
            return $this->baseUrl($request) . $value;
        }
        return null;
    }

    private function baseUrl(Request $request): string
    {
        $config = include APP_ROOT . '/app/config/app.php';
        $base = rtrim((string)($config['url'] ?? ''), '/');
        if ($base !== '') {
            return $base;
        }

        $scheme = (!empty($request->server['HTTPS']) && $request->server['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $request->server['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    private function profileOpenGraph(Request $request, array $user, bool $restricted, string $canonical, string $description): array
    {
        $image = $this->absoluteAssetUrl($request, (string)($user['cover_image'] ?? ($user['avatar'] ?? '')));
        $title = $restricted
            ? 'Profile is private'
            : (string)($user['display_name'] ?: ($user['name'] ?? 'User'));

        return [
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'url' => $canonical,
            'type' => 'profile',
        ];
    }

    private function masterContactAvailability(array $user): array
    {
        try {
            /** @var MasterContactService $service */
            $service = $this->container->get(MasterContactService::class);
            return $service->publicAvailability($user);
        } catch (\Throwable $e) {
            return ['available' => false, 'settings' => []];
        }
    }
}
