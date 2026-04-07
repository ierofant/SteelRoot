<?php
declare(strict_types=1);

namespace Modules\Users\Services;

class UserAccessService
{
    private UserRepository $users;

    public function __construct(UserRepository $users)
    {
        $this->users = $users;
    }

    public function capabilityOptions(): array
    {
        return [
            'profile.extended' => 'Extended public profile',
            'gallery.submit' => 'Submit gallery works',
            'gallery.publish' => 'Publish gallery works directly',
            'favorites.manage' => 'Manage favorites',
            'comments.profile' => 'Comments on profile',
            'comments.moderate' => 'Moderate comments',
            'profile.contacts' => 'Show profile contacts',
            'profile.links' => 'Use external links',
            'profile.verified' => 'Verified badge',
            'profile.badges' => 'Special profile badges',
            'profile.moderate' => 'Moderate master profiles',
            'admin.articles' => 'Admin: manage all articles',
            'admin.articles.own' => 'Admin: manage own articles only',
            'admin.gallery' => 'Admin: manage gallery',
            'admin.news' => 'Admin: manage news',
        ];
    }

    public function groupsForUser(int $userId): array
    {
        return $this->users->groupsForUser($userId);
    }

    public function primaryGroupForUser(int $userId): ?array
    {
        return $this->users->primaryGroupForUser($userId);
    }

    public function permissionsForUser(array $user): array
    {
        $permissions = [];
        foreach ($this->users->permissionsForUser((int)($user['id'] ?? 0)) as $permission) {
            $permissions[$permission] = true;
        }
        foreach ($this->users->planCapabilitiesForUser((int)($user['id'] ?? 0)) as $permission) {
            $permissions[$permission] = true;
        }

        $role = (string)($user['role'] ?? 'user');
        if ($role === 'admin') {
            foreach (array_keys($this->capabilityOptions()) as $permission) {
                $permissions[$permission] = true;
            }
        } elseif ($role === 'editor') {
            $permissions['gallery.publish'] = true;
            $permissions['profile.extended'] = true;
            $permissions['admin.articles.own'] = true;
        }

        return array_keys($permissions);
    }

    public function can(array $user, string $permission): bool
    {
        if (($user['role'] ?? '') === 'admin') {
            return true;
        }

        $permissions = $this->permissionsForUser($user);
        return in_array($permission, $permissions, true);
    }

    public function inGroup(array $user, string $slug): bool
    {
        foreach ($this->groupsForUser((int)($user['id'] ?? 0)) as $group) {
            if (($group['slug'] ?? '') === $slug) {
                return true;
            }
        }

        return false;
    }
}
