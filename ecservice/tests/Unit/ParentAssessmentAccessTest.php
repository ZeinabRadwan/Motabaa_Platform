<?php

namespace Tests\Unit;

use App\Support\ParentAssessmentAccess;
use App\Support\PermissionCatalog;
use Illuminate\Http\Request;
use Tests\TestCase;

class ParentAssessmentAccessTest extends TestCase
{
    public function test_period_query_is_detected_as_assessment_request(): void
    {
        $request = Request::create('/api/goals', 'GET', [
            'category' => 'educational',
            'period' => '0',
        ]);

        $this->assertTrue(ParentAssessmentAccess::requestUsesPeriodAssessment($request));
    }

    public function test_pdf_period_assessment_is_detected(): void
    {
        $request = Request::create('/api/goals', 'GET', [
            'category' => 'educational',
            'pdf' => 'period_assessment',
        ]);

        $this->assertTrue(ParentAssessmentAccess::requestUsesPeriodAssessment($request));
    }

    public function test_regular_goal_list_without_period_is_not_assessment_request(): void
    {
        $request = Request::create('/api/goals', 'GET', [
            'category' => 'educational',
            'status' => 'active',
        ]);

        $this->assertFalse(ParentAssessmentAccess::requestUsesPeriodAssessment($request));
    }

    public function test_independent_period_query_is_detected_as_assessment_request(): void
    {
        $request = Request::create('/api/goals', 'GET', [
            'category' => 'independent',
            'period' => '1',
        ]);

        $this->assertTrue(ParentAssessmentAccess::requestUsesPeriodAssessment($request));
    }

    public function test_non_assessment_pdf_is_not_restricted_by_assessment_permissions(): void
    {
        $this->assertTrue(ParentAssessmentAccess::staffCanExportPeriodAssessmentPdf('case_planning'));
        $this->assertTrue(ParentAssessmentAccess::staffCanExportPeriodAssessmentPdf(null));
    }

    public function test_period_assessment_pdf_requires_a_user(): void
    {
        foreach (array_keys(ParentAssessmentAccess::STAFF_PERIOD_PDF_PERMISSIONS) as $pdf) {
            $this->assertFalse(ParentAssessmentAccess::staffCanExportPeriodAssessmentPdf($pdf));
        }
    }

    public function test_period_assessment_pdf_accepts_view_or_edit_permission(): void
    {
        $this->assertSame(
            ['access_independent-evaluations', 'edit_independent-evaluations'],
            ParentAssessmentAccess::STAFF_PERIOD_PDF_PERMISSIONS['independent_period_assessment']
        );
    }

    public function test_independent_parent_permission_is_in_parent_portal_catalog(): void
    {
        $definition = PermissionCatalog::definitions()[ParentAssessmentAccess::PERM_INDEPENDENT] ?? null;

        $this->assertNotNull($definition);
        $this->assertSame('parent_portal', $definition['module']);
        $this->assertSame('access', $definition['action']);
    }
}
