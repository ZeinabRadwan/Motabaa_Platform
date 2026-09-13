<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    /**
     * Canonical Motabaah permissions grouped by real platform modules.
     * Names match frontend CASL keys. Existing rows are never deleted.
     */
    public static function modules(): array
    {
        return [
            'cases' => [
                'label' => 'Cases',
                'label_local' => 'الحالات',
                'permissions' => [
                    'access_cases' => ['Access cases', 'عرض الحالات'],
                    'show_cases' => ['View case details', 'عرض تفاصيل الحالة'],
                    'edit_cases' => ['Create and edit cases', 'إضافة وتعديل الحالات'],
                    'admin_cases' => ['Delete and restore cases', 'حذف واستعادة الحالات'],
                ],
            ],
            'assessments_cases' => [
                'label' => 'Case assessments',
                'label_local' => 'تقييم الحالات',
                'permissions' => [
                    'access_assessments_cases' => ['Access case assessments', 'عرض تقييم الحالات'],
                    'apply_assessments_cases' => ['Apply case assessments', 'تطبيق تقييم الحالات'],
                ],
            ],
            'education-goals' => [
                'label' => 'Qualifying programme plans',
                'label_local' => 'الخطط الفردية',
                'permissions' => [
                    'access_education-goals' => ['Access individual plans', 'عرض الخطط الفردية'],
                    'edit_education-goals' => ['Create and edit individual plans', 'إضافة وتعديل الخطط الفردية'],
                    'admin_education-goals' => ['Delete and restore individual plans', 'حذف واستعادة الخطط الفردية'],
                ],
            ],
            'education-sessions' => [
                'label' => 'Qualifying programme sessions',
                'label_local' => 'جلسات التأهيل',
                'permissions' => [
                    'access_education-sessions' => ['Access qualifying sessions', 'عرض جلسات التأهيل'],
                    'edit_education-sessions' => ['Run qualifying sessions', 'تنفيذ جلسات التأهيل'],
                    'admin_education-sessions' => ['Delete qualifying session data', 'حذف بيانات جلسات التأهيل'],
                ],
            ],
            'education-evaluations' => [
                'label' => 'Qualifying programme assessments',
                'label_local' => 'تقييم التأهيل',
                'permissions' => [
                    'access_education-evaluations' => ['Access qualifying assessments', 'عرض تقييم التأهيل'],
                    'edit_education-evaluations' => ['Edit qualifying assessments', 'تعديل تقييم التأهيل'],
                ],
            ],
            'treatment-goals' => [
                'label' => 'Treatment plans',
                'label_local' => 'الخطط العلاجية',
                'permissions' => [
                    'access_treatment-goals' => ['Access treatment plans', 'عرض الخطط العلاجية'],
                    'edit_treatment-goals' => ['Create and edit treatment plans', 'إضافة وتعديل الخطط العلاجية'],
                    'admin_treatment-goals' => ['Delete and restore treatment plans', 'حذف واستعادة الخطط العلاجية'],
                ],
            ],
            'treatment-sessions' => [
                'label' => 'Treatment sessions',
                'label_local' => 'الجلسات العلاجية',
                'permissions' => [
                    'access_treatment-sessions' => ['Access treatment sessions', 'عرض الجلسات العلاجية'],
                    'edit_treatment-sessions' => ['Run treatment sessions', 'تنفيذ الجلسات العلاجية'],
                    'admin_treatment-sessions' => ['Delete treatment session data', 'حذف بيانات الجلسات العلاجية'],
                ],
            ],
            'treatment-evaluations' => [
                'label' => 'Treatment assessments',
                'label_local' => 'تقييم الخدمات العلاجية',
                'permissions' => [
                    'access_treatment-evaluations' => ['Access treatment assessments', 'عرض تقييم الخدمات العلاجية'],
                    'edit_treatment-evaluations' => ['Edit treatment assessments', 'تعديل تقييم الخدمات العلاجية'],
                ],
            ],
            'independent-goals' => [
                'label' => 'Independent skills',
                'label_local' => 'المهارات الاستقلالية',
                'permissions' => [
                    'access_independent-goals' => ['Access independent skills', 'عرض المهارات الاستقلالية'],
                    'edit_independent-goals' => ['Create and edit independent skills', 'إضافة وتعديل المهارات الاستقلالية'],
                    'admin_independent-goals' => ['Delete and restore independent skills', 'حذف واستعادة المهارات الاستقلالية'],
                ],
            ],
            'independent-sessions' => [
                'label' => 'Independent skill sessions',
                'label_local' => 'جلسات المهارات الاستقلالية',
                'permissions' => [
                    'access_independent-sessions' => ['Access independent sessions', 'عرض جلسات المهارات الاستقلالية'],
                    'edit_independent-sessions' => ['Run independent sessions', 'تنفيذ جلسات المهارات الاستقلالية'],
                    'admin_independent-sessions' => ['Delete independent session data', 'حذف بيانات جلسات المهارات الاستقلالية'],
                ],
            ],
            'independent-evaluations' => [
                'label' => 'Independent skill assessments',
                'label_local' => 'تقييم المهارات الاستقلالية',
                'permissions' => [
                    'access_independent-evaluations' => ['Access independent assessments', 'عرض تقييم المهارات الاستقلالية'],
                    'edit_independent-evaluations' => ['Edit independent assessments', 'تعديل تقييم المهارات الاستقلالية'],
                ],
            ],
            'attendance' => [
                'label' => 'Case attendance',
                'label_local' => 'حضور الحالات',
                'permissions' => [
                    'access_attendance' => ['Access case attendance', 'عرض حضور الحالات'],
                    'edit_attendance' => ['Record case attendance', 'تسجيل حضور الحالات'],
                    'admin_attendance' => ['Manage case attendance', 'إدارة حضور الحالات'],
                ],
            ],
            'parents' => [
                'label' => 'Parents',
                'label_local' => 'أولياء الأمور',
                'permissions' => [
                    'access_parents' => ['Access parents', 'عرض أولياء الأمور'],
                    'show_parents' => ['View parent details', 'عرض تفاصيل ولي الأمر'],
                    'edit_parents' => ['Create and edit parents', 'إضافة وتعديل أولياء الأمور'],
                    'admin_parents' => ['Delete and restore parents', 'حذف واستعادة أولياء الأمور'],
                ],
            ],
            'users' => [
                'label' => 'Career staff',
                'label_local' => 'شؤون الموظفين',
                'permissions' => [
                    'access_users' => ['Access staff', 'عرض الموظفين'],
                    'show_users' => ['View staff details', 'عرض تفاصيل الموظف'],
                    'edit_users' => ['Create and edit staff', 'إضافة وتعديل الموظفين'],
                    'admin_users' => ['Delete and restore staff', 'حذف واستعادة الموظفين'],
                ],
            ],
            'employees-attendance' => [
                'label' => 'Staff attendance',
                'label_local' => 'حضور الموظفين',
                'permissions' => [
                    'access_employees-attendance' => ['Access staff attendance', 'عرض حضور الموظفين'],
                    'edit_employees-attendance' => ['Record staff attendance', 'تسجيل حضور الموظفين'],
                    'admin_employees-attendance' => ['Manage staff attendance', 'إدارة حضور الموظفين'],
                ],
            ],
            'meetings' => [
                'label' => 'Meeting rooms',
                'label_local' => 'قاعات الاجتماعات',
                'permissions' => [
                    'access_meetings' => ['Access meeting rooms', 'عرض قاعات الاجتماعات'],
                    'show_meetings' => ['View meeting details', 'عرض تفاصيل الاجتماع'],
                    'edit_meetings' => ['Create and edit meetings', 'إضافة وتعديل الاجتماعات'],
                    'admin_meetings' => ['Delete and restore meetings', 'حذف واستعادة الاجتماعات'],
                ],
            ],
            'study-fees' => [
                'label' => 'Study fees',
                'label_local' => 'الرسوم الدراسية',
                'permissions' => [
                    'access_study-fees' => ['Access study fees', 'عرض الرسوم الدراسية'],
                    'edit_study-fees' => ['Create and edit fees and payments', 'إضافة وتعديل الرسوم والمدفوعات'],
                    'admin_study-fees' => ['Delete and restore fees and payments', 'حذف واستعادة الرسوم والمدفوعات'],
                ],
            ],
            'centers' => [
                'label' => 'Centers',
                'label_local' => 'المراكز',
                'permissions' => [
                    'access_centers' => ['Access centers', 'عرض المراكز'],
                    'show_centers' => ['View center details', 'عرض تفاصيل المركز'],
                    'edit_centers' => ['Create and edit centers', 'إضافة وتعديل المراكز'],
                    'admin_centers' => ['Delete and restore centers', 'حذف واستعادة المراكز'],
                ],
            ],
            'roles' => [
                'label' => 'Roles and permissions',
                'label_local' => 'الوظائف والصلاحيات',
                'permissions' => [
                    'access_roles' => ['Access roles and permissions', 'عرض الوظائف والصلاحيات'],
                    'show_roles' => ['View role permissions', 'عرض صلاحيات الوظيفة'],
                    'edit_roles' => ['Create and edit roles', 'إضافة وتعديل الوظائف'],
                    'admin_roles' => ['Delete roles', 'حذف الوظائف'],
                ],
            ],
            'qualifying-classes' => [
                'label' => 'Qualifying classes',
                'label_local' => 'الفصول التأهيلية',
                'permissions' => [
                    'access_qualifying-classes' => ['Access qualifying classes', 'عرض الفصول التأهيلية'],
                    'show_qualifying-classes' => ['View class details', 'عرض تفاصيل الفصل'],
                    'edit_qualifying-classes' => ['Create and edit classes', 'إضافة وتعديل الفصول'],
                    'admin_qualifying-classes' => ['Delete and restore classes', 'حذف واستعادة الفصول'],
                ],
            ],
            'scales' => [
                'label' => 'Scales',
                'label_local' => 'المقاييس',
                'permissions' => [
                    'access_scales' => ['Access scales', 'عرض المقاييس'],
                    'edit_scales' => ['Create and edit scales', 'إضافة وتعديل المقاييس'],
                    'admin_scales' => ['Delete and restore scales', 'حذف واستعادة المقاييس'],
                ],
            ],
            'operation-plans' => [
                'label' => 'Operational plans',
                'label_local' => 'الخطة التشغيلية',
                'permissions' => [
                    'access_operation-plans' => ['Access operational plans', 'عرض الخطة التشغيلية'],
                    'show_operation-plans' => ['View operational plan details', 'عرض تفاصيل الخطة التشغيلية'],
                    'edit_operation-plans' => ['Create and edit operational plans', 'إضافة وتعديل الخطة التشغيلية'],
                    'admin_operation-plans' => ['Delete and restore operational plans', 'حذف واستعادة الخطة التشغيلية'],
                ],
            ],
            'logs' => [
                'label' => 'Logs',
                'label_local' => 'سجل العمليات',
                'permissions' => [
                    'access_logs' => ['Access logs', 'عرض سجل العمليات'],
                    'admin_logs' => ['Manage logs', 'إدارة سجل العمليات'],
                ],
            ],
            'questionnaires' => [
                'label' => 'Questionnaires',
                'label_local' => 'الاستبيانات',
                'permissions' => [
                    'access_questionnaires' => ['Access questionnaires', 'عرض الاستبيانات'],
                    'show_questionnaires' => ['View questionnaire details', 'عرض تفاصيل الاستبيان'],
                    'edit_questionnaires' => ['Create and edit questionnaires', 'إضافة وتعديل الاستبيانات'],
                    'admin_questionnaires' => ['Delete and restore questionnaires', 'حذف واستعادة الاستبيانات'],
                ],
            ],
            'system' => [
                'label' => 'System',
                'label_local' => 'النظام',
                'permissions' => [
                    'admin_system' => ['System administration', 'إدارة النظام'],
                ],
            ],
        ];
    }

    public static function definitions(): array
    {
        $definitions = [];

        foreach (self::modules() as $module => $config) {
            foreach ($config['permissions'] as $name => $labels) {
                $definitions[$name] = [
                    'name' => $name,
                    'name_en' => $labels[0],
                    'name_local' => $labels[1],
                    'module' => $module,
                    'module_label' => $config['label'],
                    'module_label_local' => $config['label_local'],
                    'action' => self::actionOf($name),
                ];
            }
        }

        return $definitions;
    }

    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    public static function actionOf(string $name): string
    {
        foreach (['access', 'show', 'edit', 'admin', 'apply'] as $action) {
            if (str_starts_with($name, $action.'_')) {
                return $action;
            }
        }

        return 'other';
    }

    public static function sync(): array
    {
        $created = [];
        $updated = [];

        foreach (self::definitions() as $name => $definition) {
            $permission = Permission::where('name', $name)->where('guard_name', 'web')->first();
            if (! $permission) {
                Permission::create([
                    'name' => $name,
                    'name_local' => $definition['name_local'],
                    'guard_name' => 'web',
                ]);
                $created[] = $name;
                continue;
            }

            if ($permission->name_local !== $definition['name_local']) {
                $permission->name_local = $definition['name_local'];
                $permission->save();
                $updated[] = $name;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return compact('created', 'updated');
    }

    public static function syncIfNeeded(): array
    {
        $names = self::names();
        $existing = Permission::where('guard_name', 'web')->whereIn('name', $names)->pluck('name');
        $missingLocal = Permission::where('guard_name', 'web')
            ->whereIn('name', $names)
            ->where(function ($query) {
                $query->whereNull('name_local')->orWhere('name_local', '');
            })
            ->exists();

        if ($existing->count() === count($names) && ! $missingLocal) {
            return ['created' => [], 'updated' => []];
        }

        $result = self::sync();
        self::grantCatalogToAdmin();

        return $result;
    }

    public static function grantCatalogToAdmin(): void
    {
        $admin = Role::where('default_name', 'admin')->where('guard_name', 'web')->first();
        if (! $admin) {
            return;
        }

        $permissions = Permission::where('guard_name', 'web')
            ->whereIn('name', self::names())
            ->get();

        if ($permissions->isNotEmpty()) {
            $admin->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public static function groupedForUi($permissions, array $rolesByPermissionId = []): array
    {
        $definitions = self::definitions();
        $locale = app()->getLocale();
        $modules = [];
        $seen = [];

        foreach (self::modules() as $key => $config) {
            $modules[$key] = [
                'key' => $key,
                'label' => $locale === 'ar' ? $config['label_local'] : $config['label'],
                'permissions' => [],
            ];
        }

        foreach ($permissions as $permission) {
            $name = $permission->name;
            $seen[$name] = true;
            $definition = $definitions[$name] ?? null;
            $module = $definition['module'] ?? 'other';

            if (! isset($modules[$module])) {
                $modules[$module] = [
                    'key' => $module,
                    'label' => $locale === 'ar' ? 'أخرى' : 'Other',
                    'permissions' => [],
                ];
            }

            $label = $definition
                ? ($locale === 'ar' ? $definition['name_local'] : $definition['name_en'])
                : (($locale === 'ar' && $permission->name_local)
                    ? $permission->name_local
                    : ucwords(str_replace(['_', '-'], ' ', $name)));

            $modules[$module]['permissions'][] = [
                'id' => $permission->id,
                'name' => $name,
                'name_local' => $permission->name_local,
                'action' => $definition['action'] ?? self::actionOf($name),
                'label' => $label,
                'roles' => $rolesByPermissionId[$permission->id] ?? [],
            ];
        }

        return array_values(array_filter($modules, function ($module) {
            return count($module['permissions']) > 0;
        }));
    }
}
