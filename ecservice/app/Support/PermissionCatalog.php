<?php

namespace App\Support;

use App\Models\User;
use App\Support\Hr\HrBranchScope;
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
                    'access_cases_mine' => ['View assigned cases only', 'عرض حالاته فقط'],
                    'access_cases_all' => ['View all center cases', 'عرض جميع الحالات'],
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
            'hr-organization' => [
                'label' => 'HR: branches, departments and work locations',
                'label_local' => 'الموارد البشرية: الفروع والأقسام ومواقع العمل',
                'permissions' => [
                    'access_hr-organization' => ['View branches, departments and work locations', 'عرض الفروع والأقسام ومواقع العمل'],
                    'edit_hr-organization' => ['Create and edit branches, departments and work locations', 'إضافة وتعديل الفروع والأقسام ومواقع العمل'],
                    'admin_hr-organization' => ['Delete and restore branches, departments and work locations', 'حذف واستعادة الفروع والأقسام ومواقع العمل'],
                ],
            ],
            'hr-all-branches' => [
                'label' => 'HR: data of all branches',
                'label_local' => 'الموارد البشرية: بيانات جميع الفروع',
                'permissions' => [
                    'access_hr-all-branches' => [
                        'Access HR data of every branch (without it, only branches the user manages)',
                        'الوصول إلى بيانات الموارد البشرية لجميع الفروع (بدونها يقتصر الوصول على الفروع التي يديرها المستخدم)',
                    ],
                ],
            ],
            'hr-employees' => [
                'label' => 'HR: employee profiles',
                'label_local' => 'الموارد البشرية: ملفات الموظفين',
                'permissions' => [
                    'access_hr-employees' => ['View employee HR profiles', 'عرض ملفات الموظفين'],
                    'edit_hr-employees' => ['Create and edit employee HR profiles', 'إضافة وتعديل ملفات الموظفين'],
                    'admin_hr-employees' => ['Activate and deactivate employees', 'تفعيل وإيقاف الموظفين'],
                ],
            ],
            'hr-compensation' => [
                'label' => 'HR: salaries and insurance',
                'label_local' => 'الموارد البشرية: الرواتب والتأمينات',
                'permissions' => [
                    'access_hr-compensation' => ['View employee salaries and insurance', 'عرض رواتب وتأمينات الموظفين'],
                    'edit_hr-compensation' => ['Record salary and insurance changes', 'تسجيل تغييرات الرواتب والتأمينات'],
                ],
            ],
            'hr-documents' => [
                'label' => 'HR: employee documents',
                'label_local' => 'الموارد البشرية: مستندات الموظفين',
                'permissions' => [
                    'access_hr-documents' => ['View and download employee documents', 'عرض وتنزيل مستندات الموظفين'],
                    'edit_hr-documents' => ['Upload and edit employee documents', 'رفع وتعديل مستندات الموظفين'],
                    'admin_hr-documents' => ['Delete employee documents', 'حذف مستندات الموظفين'],
                ],
            ],
            'hr-attendance-setup' => [
                'label' => 'HR: shifts, attendance policies and holidays',
                'label_local' => 'الموارد البشرية: الورديات وسياسات الحضور والعطلات',
                'permissions' => [
                    'access_hr-attendance-setup' => ['View shifts, attendance policies and holidays', 'عرض الورديات وسياسات الحضور والعطلات'],
                    'edit_hr-attendance-setup' => ['Create and edit shifts, attendance policies and holidays', 'إضافة وتعديل الورديات وسياسات الحضور والعطلات'],
                    'admin_hr-attendance-setup' => ['Delete and restore shifts, attendance policies and holidays', 'حذف واستعادة الورديات وسياسات الحضور والعطلات'],
                ],
            ],
            'hr-attendance-config' => [
                'label' => 'HR: employee attendance configuration',
                'label_local' => 'الموارد البشرية: إعداد حضور الموظفين',
                'permissions' => [
                    'edit_hr-attendance-config' => [
                        'Change employee attendance method, GPS verification, policy and grace period',
                        'تغيير طريقة الحضور والتحقق بالموقع والسياسة وفترة السماح للموظفين',
                    ],
                ],
            ],
            'hr-self-attendance' => [
                'label' => 'HR: my electronic attendance',
                'label_local' => 'الموارد البشرية: حضوري الإلكتروني',
                'permissions' => [
                    'apply_hr-self-attendance' => [
                        'Check in and out electronically from the website (own attendance, electronic method only)',
                        'تسجيل الحضور والانصراف إلكترونياً من الموقع (للحضور الشخصي، لطريقة الحضور الإلكترونية فقط)',
                    ],
                ],
            ],
            'hr-attendance-records' => [
                'label' => 'HR: attendance records',
                'label_local' => 'الموارد البشرية: سجلات الحضور',
                'permissions' => [
                    'access_hr-attendance-records' => [
                        'View the attendance records of every method (electronic and manual) and their change history',
                        'عرض سجلات الحضور بجميع الطرق (الإلكترونية واليدوية) وسجل تعديلاتها',
                    ],
                    'edit_hr-attendance-records' => [
                        'Enter manual attendance (single, bulk, Excel import) and correct missing check-in / check-out within the allowed period',
                        'إدخال الحضور اليدوي (فردي، جماعي، استيراد Excel) وتصحيح الحضور أو الانصراف المفقود خلال الفترة المسموحة',
                    ],
                    'admin_hr-attendance-records' => [
                        'Enter and correct attendance older than the allowed period (historical records)',
                        'إدخال وتصحيح الحضور الأقدم من الفترة المسموحة (السجلات التاريخية)',
                    ],
                ],
            ],
            'hr-own-attendance-records' => [
                'label' => 'HR: own attendance records',
                'label_local' => 'الموارد البشرية: سجلات الحضور الشخصية',
                'permissions' => [
                    'edit_hr-own-attendance-records' => [
                        'Enter and correct one\'s own attendance records (in addition to editing attendance records)',
                        'إدخال وتصحيح سجلات الحضور الخاصة بالمستخدم نفسه (إضافة إلى صلاحية تعديل سجلات الحضور)',
                    ],
                ],
            ],
            'hr-location-monitoring' => [
                'label' => 'HR: location monitoring',
                'label_local' => 'الموارد البشرية: مراقبة الموقع',
                'permissions' => [
                    'access_hr-location-monitoring' => [
                        'View attendance sessions, location checks, outside-radius alerts and mobile devices',
                        'عرض جلسات الحضور وعمليات التحقق من الموقع وتنبيهات الخروج من النطاق والأجهزة المحمولة',
                    ],
                    'edit_hr-location-monitoring' => [
                        'Review outside-radius alerts and approve or revoke mobile devices',
                        'مراجعة تنبيهات الخروج من النطاق واعتماد أو إلغاء الأجهزة المحمولة',
                    ],
                ],
            ],
            'hr-attendance-processing' => [
                'label' => 'HR: attendance processing and payroll periods',
                'label_local' => 'الموارد البشرية: معالجة الحضور وفترات الرواتب',
                'permissions' => [
                    'access_hr-attendance-processing' => [
                        'View processed attendance (absence, lateness, exceptions) and payroll attendance periods',
                        'عرض الحضور المعالج (الغياب والتأخير والاستثناءات) وفترات حضور الرواتب',
                    ],
                    'edit_hr-attendance-processing' => [
                        'Process attendance, correct attendance days with a reason and prepare payroll periods',
                        'معالجة الحضور وتصحيح أيام الحضور مع ذكر السبب وإعداد فترات الرواتب',
                    ],
                    'admin_hr-attendance-processing' => [
                        'Approve and reopen payroll attendance periods',
                        'اعتماد وإعادة فتح فترات حضور الرواتب',
                    ],
                ],
            ],
            'hr-leave' => [
                'label' => 'HR: leave management',
                'label_local' => 'الموارد البشرية: إدارة الإجازات',
                'permissions' => [
                    'access_hr-leave' => ['View leave requests, balances and leave types', 'عرض طلبات وأرصدة وأنواع الإجازات'],
                    'edit_hr-leave' => [
                        'Request leave on behalf of employees, adjust balances and cancel pending requests',
                        'طلب الإجازات نيابة عن الموظفين وتعديل الأرصدة وإلغاء الطلبات المعلقة',
                    ],
                    'admin_hr-leave' => [
                        'Configure leave types, record approved leave and cancel approved leave',
                        'إعداد أنواع الإجازات وتسجيل إجازات معتمدة وإلغاء الإجازات المعتمدة',
                    ],
                    'apply_hr-leave' => [
                        'Approve leave requests as HR (HR approval step)',
                        'اعتماد طلبات الإجازة كموارد بشرية (خطوة اعتماد الموارد البشرية)',
                    ],
                ],
            ],
            'hr-self-leave' => [
                'label' => 'HR: my leave',
                'label_local' => 'الموارد البشرية: إجازاتي',
                'permissions' => [
                    'apply_hr-self-leave' => [
                        'Request own leave and view own balances and leave history',
                        'طلب الإجازات الشخصية وعرض الأرصدة وسجل الإجازات',
                    ],
                ],
            ],
            'hr-additional-days' => [
                'label' => 'HR: additional working days',
                'label_local' => 'الموارد البشرية: أيام العمل الإضافية',
                'permissions' => [
                    'access_hr-additional-days' => [
                        'View additional working days, their history and payroll status',
                        'عرض أيام العمل الإضافية وسجلها وحالتها في الرواتب',
                    ],
                    'edit_hr-additional-days' => [
                        'Add additional working days, edit pending records, submit them for approval and cancel unapproved records',
                        'إضافة أيام عمل إضافية وتعديل السجلات المعلقة وإرسالها للاعتماد وإلغاء السجلات غير المعتمدة',
                    ],
                    'admin_hr-additional-days' => [
                        'Cancel approved additional working days that are not paid yet',
                        'إلغاء أيام العمل الإضافية المعتمدة التي لم تصرف بعد',
                    ],
                    'apply_hr-additional-days' => [
                        'Approve additional working days as HR (HR approval step)',
                        'اعتماد أيام العمل الإضافية كموارد بشرية (خطوة اعتماد الموارد البشرية)',
                    ],
                ],
            ],
            'hr-settings' => [
                'label' => 'HR: settings',
                'label_local' => 'الموارد البشرية: الإعدادات',
                'permissions' => [
                    'access_hr-settings' => ['View HR settings', 'عرض إعدادات الموارد البشرية'],
                    'edit_hr-settings' => ['Edit HR settings', 'تعديل إعدادات الموارد البشرية'],
                ],
            ],
            'hr-approvals' => [
                'label' => 'HR: approvals',
                'label_local' => 'الموارد البشرية: الموافقات',
                'permissions' => [
                    'access_hr-approvals' => ['View all HR approval requests', 'عرض جميع طلبات الموافقة'],
                    'apply_hr-approvals' => ['Approve HR requests (default approver)', 'اعتماد طلبات الموارد البشرية (المعتمد الافتراضي)'],
                    'edit_hr-approvals' => ['Configure approval workflows', 'إعداد مسارات الموافقة'],
                    'admin_hr-approvals' => ['Cancel any approval request', 'إلغاء أي طلب موافقة'],
                ],
            ],
            'hr-audit' => [
                'label' => 'HR: audit log',
                'label_local' => 'الموارد البشرية: سجل التدقيق',
                'permissions' => [
                    'access_hr-audit' => ['View HR audit log', 'عرض سجل تدقيق الموارد البشرية'],
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
            'center-activities' => [
                'label' => 'Activities and events',
                'label_local' => 'الأنشطة والفعاليات',
                'permissions' => [
                    'access_center-activities' => ['View activities and events', 'عرض الأنشطة والفعاليات'],
                    'add_center-activities' => ['Add activity or event', 'إضافة نشاط/فعالية'],
                    'edit_center-activities' => ['Edit activity or event', 'تعديل نشاط/فعالية'],
                    'delete_center-activities' => ['Delete activity or event', 'حذف نشاط/فعالية'],
                    'manage_center-activities_content' => ['Manage activity content (comments and media)', 'إدارة محتوى الفعالية'],
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
            'parent_portal' => [
                'label' => 'Parent portal',
                'label_local' => 'بوابة ولي الأمر',
                'permissions' => [
                    'access_parent-education-evaluations' => [
                        'Parent access: rehabilitation program assessment',
                        'ولي الأمر: تقييم البرنامج التأهيلي',
                    ],
                    'access_parent-treatment-evaluations' => [
                        'Parent access: support services assessment',
                        'ولي الأمر: تقييم خدمات الدعم',
                    ],
                    'access_parent-independent-evaluations' => [
                        'Parent access: independent skills assessment',
                        'ولي الأمر: تقييم المهارات الاستقلالية',
                    ],
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
        foreach (['access', 'show', 'edit', 'admin', 'apply', 'add', 'delete', 'manage'] as $action) {
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

        $migration = self::migrateCasesViewPermissions();
        $centerActivitiesMigration = self::migrateCenterActivitiesPermissions();
        $parentAssessmentMigration = self::migrateParentAssessmentPermissions();
        $hrBranchScopeMigration = in_array(HrBranchScope::ALL_BRANCHES_PERMISSION, $created, true)
            ? self::migrateHrBranchScopePermission()
            : null;
        $hrAttendanceConfigMigration = in_array(self::HR_ATTENDANCE_CONFIG_PERMISSION, $created, true)
            ? self::migrateHrAttendanceConfigPermission()
            : null;

        return array_merge(compact('created', 'updated'), [
            'cases_view_migration' => $migration,
            'center_activities_migration' => $centerActivitiesMigration,
            'parent_assessment_migration' => $parentAssessmentMigration,
            'hr_branch_scope_migration' => $hrBranchScopeMigration,
            'hr_attendance_config_migration' => $hrAttendanceConfigMigration,
        ]);
    }

    public const HR_ATTENDANCE_CONFIG_PERMISSION = 'edit_hr-attendance-config';

    /**
     * Runs once, when edit_hr-attendance-config is created: roles that could already change attendance settings
     * (through edit_hr-employees) keep that ability.
     */
    public static function migrateHrAttendanceConfigPermission(): array
    {
        $stats = ['roles_granted' => 0];
        $name = self::HR_ATTENDANCE_CONFIG_PERMISSION;

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if (canAny(['edit_hr-employees', 'admin_hr-employees'], $role) && ! $role->hasPermissionTo($name, 'web')) {
                $role->givePermissionTo($name);
                $stats['roles_granted']++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    /**
     * Runs once, when access_hr-all-branches is created: roles that already administer HR keep center-wide
     * access instead of being narrowed to the branches they manage.
     */
    public static function migrateHrBranchScopePermission(): array
    {
        $stats = ['roles_granted' => 0];
        $name = HrBranchScope::ALL_BRANCHES_PERMISSION;

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if (canAny(['admin_hr-organization', 'admin_hr-employees'], $role) && ! $role->hasPermissionTo($name, 'web')) {
                $role->givePermissionTo($name);
                $stats['roles_granted']++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    /**
     * Maps legacy center-activities permissions to the simplified catalog (runs on sync).
     */
    public static function migrateCenterActivitiesPermissions(): array
    {
        $add = CenterActivityAccess::PERM_ADD;
        $edit = CenterActivityAccess::PERM_EDIT;
        $delete = CenterActivityAccess::PERM_DELETE;
        $manage = CenterActivityAccess::PERM_MANAGE_CONTENT;
        $legacy = CenterActivityAccess::LEGACY_PERMISSIONS;

        $stats = ['roles_updated' => 0, 'legacy_revoked' => 0];

        $grant = function ($model, string $guard, array $names) {
            if (! method_exists($model, 'givePermissionTo')) {
                return;
            }
            foreach ($names as $name) {
                if (Permission::where('name', $name)->where('guard_name', $guard)->exists()
                    && ! $model->hasPermissionTo($name, $guard)) {
                    $model->givePermissionTo($name);
                }
            }
        };

        $revokeLegacy = function ($model, string $guard) use ($legacy, &$stats) {
            if (! method_exists($model, 'revokePermissionTo')) {
                return;
            }
            foreach ($legacy as $name) {
                if ($model->hasPermissionTo($name, $guard)) {
                    $model->revokePermissionTo($name);
                    $stats['legacy_revoked']++;
                }
            }
        };

        $migrateModel = function ($model, string $guard) use ($add, $edit, $delete, $manage, $grant, $revokeLegacy, &$stats) {
            $hadLegacyAdmin = $model->hasPermissionTo('admin_center-activities', $guard);
            $hadLegacyEdit = $model->hasPermissionTo($edit, $guard);
            $hadLegacyContent = canAny([
                'add_center-activities_entry',
                'upload_center-activities_image',
                'upload_center-activities_video',
                'upload_center-activities_file',
            ], $model);

            $toGrant = [];
            if ($hadLegacyEdit) {
                $toGrant[] = $add;
                $toGrant[] = $edit;
            }
            if ($hadLegacyAdmin) {
                $toGrant[] = $delete;
                $toGrant[] = $manage;
            }
            if ($hadLegacyContent) {
                $toGrant[] = $manage;
            }

            if ($toGrant !== []) {
                $grant($model, $guard, array_unique($toGrant));
                $stats['roles_updated']++;
            }

            $revokeLegacy($model, $guard);
        };

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            $migrateModel($role, 'web');
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    /**
     * Grant parent-only assessment permissions when the parent role already had staff assessment access.
     */
    public static function migrateParentAssessmentPermissions(): array
    {
        $pairs = [
            'access_education-evaluations' => ParentAssessmentAccess::PERM_EDUCATION,
            'access_treatment-evaluations' => ParentAssessmentAccess::PERM_TREATMENT,
        ];

        $stats = ['roles_granted' => 0, 'users_granted' => 0];

        $grantMissing = function ($model, string $guard) use ($pairs): bool {
            if (! method_exists($model, 'hasPermissionTo') || ! method_exists($model, 'givePermissionTo')) {
                return false;
            }

            $granted = false;
            foreach ($pairs as $staffPerm => $parentPerm) {
                if ($model->hasPermissionTo($staffPerm, $guard) && ! $model->hasPermissionTo($parentPerm, $guard)) {
                    $model->givePermissionTo($parentPerm);
                    $granted = true;
                }
            }

            return $granted;
        };

        foreach (Role::where('default_name', 'parent')->where('guard_name', 'web')->get() as $role) {
            if ($grantMissing($role, 'web')) {
                $stats['roles_granted']++;
            }
        }

        foreach (User::whereHas('roles', fn ($q) => $q->where('default_name', 'parent'))->get() as $user) {
            if (! isParentUser($user)) {
                continue;
            }
            if ($grantMissing($user, 'web')) {
                $stats['users_granted']++;
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
    }

    /**
     * Maps legacy access_cases to the split view permissions (runs on sync).
     *
     * - access_cases (عرض الحالات) on a role without admin_cases → access_cases_mine
     * - admin_cases on a role → access_cases_all (full center list; same as previous admin bypass)
     * - access_cases + admin_cases → access_cases_mine + access_cases_all, then legacy revoked
     */
    public static function migrateCasesViewPermissions(): array
    {
        $mineName = CaseListAccess::PERM_MINE;
        $allName = CaseListAccess::PERM_ALL;
        $legacyName = CaseListAccess::PERM_LEGACY;

        foreach ([$mineName, $allName] as $name) {
            if (! Permission::where('name', $name)->where('guard_name', 'web')->exists()) {
                $definition = self::definitions()[$name] ?? null;
                if ($definition) {
                    Permission::create([
                        'name' => $name,
                        'name_local' => $definition['name_local'],
                        'guard_name' => 'web',
                    ]);
                }
            }
        }

        $stats = ['roles_migrated' => 0, 'users_migrated' => 0, 'legacy_revoked' => 0];

        $apply = function ($model, string $guard) use ($mineName, $allName, $legacyName, &$stats) {
            if (! method_exists($model, 'hasPermissionTo')) {
                return;
            }

            $hasLegacy = $model->hasPermissionTo($legacyName, $guard);
            $hasAdmin = $model->hasPermissionTo('admin_cases', $guard);

            if ($hasAdmin && ! $model->hasPermissionTo($allName, $guard)) {
                $model->givePermissionTo($allName);
            }

            if ($hasLegacy) {
                if ($hasAdmin) {
                    if (! $model->hasPermissionTo($allName, $guard)) {
                        $model->givePermissionTo($allName);
                    }
                } elseif (! $model->hasPermissionTo($mineName, $guard)) {
                    $model->givePermissionTo($mineName);
                }

                $model->revokePermissionTo($legacyName);
                $stats['legacy_revoked']++;
            }
        };

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            $apply($role, 'web');
        }

        $stats['roles_migrated'] = Role::where('guard_name', 'web')
            ->whereHas('permissions', fn ($q) => $q->whereIn('name', [$mineName, $allName]))
            ->count();

        foreach (User::whereHas('permissions', fn ($q) => $q->where('name', $legacyName))->get() as $user) {
            $apply($user, 'web');
            $stats['users_migrated']++;
        }

        foreach (Role::where('guard_name', 'web')->get() as $role) {
            if ($role->hasPermissionTo('admin_cases') && ! $role->hasPermissionTo($allName)) {
                $role->givePermissionTo($allName);
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $stats;
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
