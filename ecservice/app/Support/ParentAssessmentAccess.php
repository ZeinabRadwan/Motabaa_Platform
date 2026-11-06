<?php

namespace App\Support;

use App\Models\System\System;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\Request;

class ParentAssessmentAccess
{
    public const PERM_EDUCATION = 'access_parent-education-evaluations';

    public const PERM_TREATMENT = 'access_parent-treatment-evaluations';

    public const PERM_INDEPENDENT = 'access_parent-independent-evaluations';

    /** Staff may export a period assessment PDF with view or edit access to that assessment. */
    public const STAFF_PERIOD_PDF_PERMISSIONS = [
        'period_assessment' => ['access_education-evaluations', 'edit_education-evaluations'],
        'services_period_assessment' => ['access_treatment-evaluations', 'edit_treatment-evaluations'],
        'independent_period_assessment' => ['access_independent-evaluations', 'edit_independent-evaluations'],
    ];

    public static function parentCanViewEducationAssessments(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user && $user->can(self::PERM_EDUCATION);
    }

    public static function parentCanViewTreatmentAssessments(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user && $user->can(self::PERM_TREATMENT);
    }

    public static function parentCanViewIndependentAssessments(?User $user = null): bool
    {
        $user = $user ?? auth()->user();

        return $user && $user->can(self::PERM_INDEPENDENT);
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentEducationAssessmentsBlocked(?User $user = null)
    {
        if (! isParentUser($user)) {
            return null;
        }

        if (self::parentCanViewEducationAssessments($user)) {
            return null;
        }

        return Term::forbiddenResponse();
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentTreatmentAssessmentsBlocked(?User $user = null)
    {
        if (! isParentUser($user)) {
            return null;
        }

        if (self::parentCanViewTreatmentAssessments($user)) {
            return null;
        }

        return Term::forbiddenResponse();
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentIndependentAssessmentsBlocked(?User $user = null)
    {
        if (! isParentUser($user)) {
            return null;
        }

        if (self::parentCanViewIndependentAssessments($user)) {
            return null;
        }

        return Term::forbiddenResponse();
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentCategoryAssessmentsBlocked(?string $category, ?User $user = null)
    {
        if ($category === System::TYPE_EDUCATIONAL) {
            return self::denyIfParentEducationAssessmentsBlocked($user);
        }

        if (GoalParentVisibility::isTreatmentCategory($category)) {
            return self::denyIfParentTreatmentAssessmentsBlocked($user);
        }

        if ($category === System::TYPE_INDEPENDENT) {
            return self::denyIfParentIndependentAssessmentsBlocked($user);
        }

        return null;
    }

    public static function staffCanExportPeriodAssessmentPdf(?string $pdf, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        $permissions = self::STAFF_PERIOD_PDF_PERMISSIONS[(string) $pdf] ?? null;

        if ($permissions === null) {
            return true;
        }

        return $user !== null && canAny($permissions, $user);
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfStaffPeriodAssessmentPdfBlocked(Request $request, ?User $user = null)
    {
        $user = $user ?? auth()->user();
        if (! $user || isParentUser($user)) {
            return null;
        }

        if (self::staffCanExportPeriodAssessmentPdf($request->pdf, $user)) {
            return null;
        }

        return Term::forbiddenResponse();
    }

    public static function requestUsesPeriodAssessment(Request $request): bool
    {
        if ($request->period !== null && $request->period !== '') {
            return true;
        }

        $pdf = (string) ($request->pdf ?? '');

        return in_array($pdf, [
            'period_assessment',
            'services_period_assessment',
            'independent_period_assessment',
        ], true);
    }

    /**
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse|null
     */
    public static function denyIfParentGoalListAssessmentRequest(Request $request, ?User $user = null)
    {
        if (! isParentUser($user) || ! self::requestUsesPeriodAssessment($request)) {
            return null;
        }

        return self::denyIfParentCategoryAssessmentsBlocked($request->category, $user);
    }
}
