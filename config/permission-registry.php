<?php

declare(strict_types=1);

return [
    'guard' => null,

    'resources' => [
        // App\Authorization\Permissions\ProductPermissionResource::class,
    ],

    'discovery' => [
        'enabled' => true,
        'paths' => [app_path('Authorization/Permissions')],
        'namespace' => 'App\\Authorization\\Permissions',
    ],

    'direct_permissions' => [
        'enabled' => false,
    ],

    'management_abilities' => [
        'create_role' => 'roles.create',
        'update_role_permissions' => 'roles.update',
        'delete_role' => 'roles.delete',
        'assign_user_roles' => 'users.assign_roles',
        'manage_direct_permissions' => 'users.manage_direct_permissions',
    ],
];
