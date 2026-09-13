<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Validator;
use Spatie\Permission\PermissionRegistrar;
use App\Models\User;
use DB;
use App\Models\System\System;
use App\Http\Controllers\Controller;
use App\Support\PermissionCatalog;
use App\Support\ReferenceCache;


class RolesController extends Controller {

    private static function permissionsGroups() {

        $modules = self::permissionModules();
        $groups = [];

        foreach ($modules as $module) {
            $group = [];
            foreach ($module['permissions'] as $permission) {
                $group[$permission['id']] = $permission['label'];
            }
            $groups[$module['label']] = $group;
        }

        return $groups;
    }

    private static function permissionModules()
    {
        $permissions = Permission::select('id', 'name', 'name_local')->orderBy('name')->get();
        $roles = Role::select('id', 'name')->orderBy('name')->get()->keyBy('id');
        $rolesByPermissionId = [];

        foreach (DB::table('role_has_permissions')->get() as $row) {
            $role = $roles->get($row->role_id);
            if (! $role) {
                continue;
            }
            $rolesByPermissionId[$row->permission_id][] = [
                'id' => $role->id,
                'name' => $role->name,
            ];
        }

        return PermissionCatalog::groupedForUi($permissions, $rolesByPermissionId);
    }


    public function getRoles(Request $request) {

        $roles = ReferenceCache::remember('roles', 'dropdown:all:'.app()->getLocale(), function () {
            return Role::query()
                ->select('id', 'name', 'default_name')
                ->where(function ($query) {
                    $query->whereNull('default_name')
                        ->orWhere('default_name', '')
                        ->orWhereNotIn('default_name', ['admin', 'manager']);
                })
                ->orderBy('name')
                ->get()
                ->toArray();
        });

        return success(['roles' => $roles]);
    }


    public function roles(Request $request) {

        PermissionCatalog::syncIfNeeded();

        if (auth()->user()) {
            auth()->user()->unsetRelation('roles');
            auth()->user()->unsetRelation('permissions');
            auth()->user()->unsetRelation('permissionsViaRoles');
        }

        if (! canAny(['access_roles', 'show_roles', 'edit_roles', 'admin_roles'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        $roles = Role::select('id', 'name', 'default_name', 'is_delectable')->orderBy('name')->get()->toArray();

        $permissions = Permission::select('id', 'name')->orderBy('name')->pluck('name', 'id')->toArray();

        $permissionModules = self::permissionModules();

        return success([
            'roles' => $roles,
            'permissions' => $permissions,
            'permissions_groups' => self::permissionsGroups(),
            'permission_modules' => $permissionModules,
        ]);
    }

    public function get(Request $request, Role $role) {

        if (! canAny(['access_roles', 'show_roles', 'edit_roles', 'admin_roles'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        $permissions = $role->permissions()->pluck( 'id')->toArray();

        return success(['permissions' => $permissions]);
    }

    public function put(Request $request, Role $role = null) {

        if (! canAny(['edit_roles', 'admin_roles'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        $validator = Validator::make($request->all(), [
            "name" => "required|unique:roles" . (($role) ? ",id,$role->id" : ""),
        ]);

        if ($validator->fails())
            return vrrors($validator);

        $old = ($role) ? $role->getAttributes() : null;

        if(!$role) $role = new Role();

        $role->name = $request->name;
        $role->guard_name = 'web';
        $role->save();
        $role->refresh();
        $role->syncPermissions($request->permissions);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ReferenceCache::bump('roles');

        // Log::log(($old) ? 'role\edit' : 'role\add', $role, $old);

        return success($role->getAttributes());
    }

    public function delete(Request $request, Role $role) {

        if (! canAny(['edit_roles', 'admin_roles'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        if ($role->users()->count() > 0) return error(System::ERROR_ITEM_NOT_EMPTY);

        $old = ($role) ? $role->getAttributes() : null;
        if($role->is_delectable == 1){
            $role->delete();

            app(PermissionRegistrar::class)->forgetCachedPermissions();
            ReferenceCache::bump('roles');
    
            // Log::log('role/delete', $role, $old);
    
            return success();
        }
        return error(422);

    }

    public function user(Request $request, User $user) {

        if (! canAny(['show_roles', 'edit_roles', 'admin_roles', 'show_users', 'edit_users', 'admin_users'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        return success([
            'roles' => $user->roles()->pluck('id')->toArray(),
            'permissions' => $user->permissions()->pluck('id')->toArray(),
        ]);
    }

    public function sync(Request $request, User $user) {

        if (! canAny(['edit_roles', 'admin_roles', 'edit_users', 'admin_users'])) {
            return error(System::HTTP_UNAUTHORIZED);
        }

        $user->syncPermissions($request->permissions);
        $user->syncRoles($request->roles);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        ReferenceCache::bump('roles');

        // Log::log('role/sync', $user);

        return success();
    }
}
