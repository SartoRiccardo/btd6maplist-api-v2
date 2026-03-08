<?php

namespace Database\Seeders\Core;

use App\Constants\FormatConstants;
use App\Models\Role;
use App\Models\RoleGrant;
use App\Models\RoleFormatPermission;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    private const array ROLES = [
        'TECHNICIAN' => 1,
        'MAPLIST_OWNER' => 2,
        'EXPERT_LIST_OWNER' => 3,
        'MAPLIST_MODERATOR' => 4,
        'EXPERT_LIST_MODERATOR' => 5,
        'REQUIRES_RECORDINGS' => 6,
        'CAN_SUBMIT' => 7,
        'BASIC_PERMS' => 14,
        'BOTB_OWNER' => 8,
        'BOTB_CURATOR' => 9,
        'BOTB_VERIFIER' => 10,
        'NOSTALGIA_OWNER' => 11,
        'NOSTALGIA_CURATOR' => 12,
        'NOSTALGIA_VERIFIER' => 13,
    ];

    private static array $roles = [
        self::ROLES['TECHNICIAN'] => ['name' => 'Technician', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['MAPLIST_OWNER'] => ['name' => 'Maplist Owner', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['EXPERT_LIST_OWNER'] => ['name' => 'Expert List Owner', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['MAPLIST_MODERATOR'] => ['name' => 'Maplist Moderator', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['EXPERT_LIST_MODERATOR'] => ['name' => 'Expert List Moderator', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['REQUIRES_RECORDINGS'] => ['name' => 'Requires Recordings', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['CAN_SUBMIT'] => ['name' => 'Can Submit', 'assign_on_create' => true, 'internal' => false],
        self::ROLES['BOTB_OWNER'] => ['name' => 'BotB Owner', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['BOTB_CURATOR'] => ['name' => 'BotB Curator', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['BOTB_VERIFIER'] => ['name' => 'BotB Verifier', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['NOSTALGIA_OWNER'] => ['name' => 'Nostalgia Owner', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['NOSTALGIA_CURATOR'] => ['name' => 'Nostalgia Curator', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['NOSTALGIA_VERIFIER'] => ['name' => 'Nostalgia Verifier', 'assign_on_create' => false, 'internal' => false],
        self::ROLES['BASIC_PERMS'] => ['name' => 'Basic Perms', 'assign_on_create' => true, 'internal' => true],
    ];

    private static array $roleGrants = [
        [self::ROLES['TECHNICIAN'], self::ROLES['MAPLIST_OWNER']],
        [self::ROLES['TECHNICIAN'], self::ROLES['EXPERT_LIST_OWNER']],
        [self::ROLES['TECHNICIAN'], self::ROLES['MAPLIST_MODERATOR']],
        [self::ROLES['TECHNICIAN'], self::ROLES['EXPERT_LIST_MODERATOR']],
        [self::ROLES['TECHNICIAN'], self::ROLES['REQUIRES_RECORDINGS']],
        [self::ROLES['TECHNICIAN'], self::ROLES['CAN_SUBMIT']],
        [self::ROLES['TECHNICIAN'], self::ROLES['BASIC_PERMS']],
        [self::ROLES['MAPLIST_OWNER'], self::ROLES['MAPLIST_MODERATOR']],
        [self::ROLES['MAPLIST_OWNER'], self::ROLES['REQUIRES_RECORDINGS']],
        [self::ROLES['MAPLIST_OWNER'], self::ROLES['CAN_SUBMIT']],
        [self::ROLES['EXPERT_LIST_OWNER'], self::ROLES['EXPERT_LIST_MODERATOR']],
        [self::ROLES['EXPERT_LIST_OWNER'], self::ROLES['REQUIRES_RECORDINGS']],
        [self::ROLES['EXPERT_LIST_OWNER'], self::ROLES['CAN_SUBMIT']],
        [self::ROLES['MAPLIST_MODERATOR'], self::ROLES['REQUIRES_RECORDINGS']],
        [self::ROLES['MAPLIST_MODERATOR'], self::ROLES['CAN_SUBMIT']],
        [self::ROLES['EXPERT_LIST_MODERATOR'], self::ROLES['REQUIRES_RECORDINGS']],
        [self::ROLES['EXPERT_LIST_MODERATOR'], self::ROLES['CAN_SUBMIT']],
        [self::ROLES['BOTB_OWNER'], self::ROLES['BOTB_CURATOR']],
        [self::ROLES['BOTB_OWNER'], self::ROLES['BOTB_VERIFIER']],
        [self::ROLES['NOSTALGIA_OWNER'], self::ROLES['NOSTALGIA_CURATOR']],
        [self::ROLES['NOSTALGIA_OWNER'], self::ROLES['NOSTALGIA_VERIFIER']],
    ];

    private static array $roleFormatPermissions = [
        self::ROLES['TECHNICIAN'] => [
            'global' => [
                'delete:map_submission',
                'edit:achievement_roles',
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'create:map_submission',
                'create:user',
                'edit:self',
                'ban:user',
                'create:completion_submission',
                'create:retro_map',
                'edit:retro_map',
                'delete:retro_map',
            ],
        ],
        self::ROLES['MAPLIST_OWNER'] => [
            FormatConstants::MAPLIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'edit:format_presentation',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
            FormatConstants::MAPLIST_ALL_VERSIONS => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'edit:format_presentation',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
            'global' => [
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['EXPERT_LIST_OWNER'] => [
            FormatConstants::EXPERT_LIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'edit:format_presentation',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
            'global' => [
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['MAPLIST_MODERATOR'] => [
            FormatConstants::MAPLIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
            FormatConstants::MAPLIST_ALL_VERSIONS => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
        ],
        self::ROLES['EXPERT_LIST_MODERATOR'] => [
            FormatConstants::EXPERT_LIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'delete:map_submission',
                'edit:achievement_roles',
            ],
        ],
        self::ROLES['REQUIRES_RECORDINGS'] => [
            'global' => [
                'require:completion_submission:recording',
            ],
        ],
        self::ROLES['CAN_SUBMIT'] => [
            'global' => [
                'create:map_submission',
                'create:completion_submission',
            ],
        ],
        self::ROLES['BOTB_OWNER'] => [
            FormatConstants::EXPERT_LIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:format_presentation',
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['BOTB_CURATOR'] => [
            FormatConstants::EXPERT_LIST => [
                'create:map',
                'edit:map',
                'delete:map',
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['BOTB_VERIFIER'] => [
            FormatConstants::EXPERT_LIST => [
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['NOSTALGIA_OWNER'] => [
            FormatConstants::NOSTALGIA_PACK => [
                'create:map',
                'edit:map',
                'delete:map',
                'edit:format_presentation',
                'create:user',
                'ban:user',
                'create:retro_map',
                'edit:retro_map',
                'delete:retro_map',
            ],
        ],
        self::ROLES['NOSTALGIA_CURATOR'] => [
            FormatConstants::NOSTALGIA_PACK => [
                'create:map',
                'edit:map',
                'delete:map',
                'create:user',
                'ban:user',
                'create:retro_map',
                'edit:retro_map',
                'delete:retro_map',
            ],
        ],
        self::ROLES['NOSTALGIA_VERIFIER'] => [
            FormatConstants::NOSTALGIA_PACK => [
                'edit:config',
                'create:completion',
                'edit:completion',
                'delete:completion',
                'create:user',
                'ban:user',
            ],
        ],
        self::ROLES['BASIC_PERMS'] => [
            'global' => [
                'edit:self',
            ],
        ],
    ];

    public function run(): void
    {
        // Seed roles
        foreach (self::$roles as $id => $role) {
            Role::updateOrCreate(
                ['id' => $id],
                array_merge(['id' => $id], $role)
            );
        }

        // Seed role grants
        foreach (self::$roleGrants as [$roleRequired, $roleCanGrant]) {
            RoleGrant::updateOrCreate(
                [
                    'role_required' => $roleRequired,
                    'role_can_grant' => $roleCanGrant,
                ]
            );
        }

        // Seed role format permissions
        foreach (self::$roleFormatPermissions as $roleId => $formatPermissions) {
            foreach ($formatPermissions as $formatKey => $permissions) {
                $formatId = $formatKey === 'global' ? null : $formatKey;
                foreach ($permissions as $permission) {
                    RoleFormatPermission::updateOrCreate(
                        [
                            'role_id' => $roleId,
                            'format_id' => $formatId,
                            'permission' => $permission,
                        ]
                    );
                }
            }
        }
    }
}
