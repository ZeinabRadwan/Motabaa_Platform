<?php

namespace Tests\Unit;

use App\Support\GoalParentVisibility;
use Tests\TestCase;

class ParentServiceTypeOptionsTest extends TestCase
{
    public function test_repeated_categories_collapse_to_one_option_each(): void
    {
        $options = GoalParentVisibility::treatmentCategoryOptions(
            ['occupational therapy', 'occupational therapy', 'pronouncement', 'occupational therapy', 'pronouncement'],
            fn (string $category) => $category
        );

        $this->assertSame(['occupational therapy', 'pronouncement'], array_column($options, 'value'));
    }

    public function test_case_and_whitespace_variants_map_to_canonical_value(): void
    {
        $this->assertSame(
            ['physical therapy', 'occupational therapy'],
            GoalParentVisibility::uniqueTreatmentCategories([
                'physical therapy',
                'Physical Therapy ',
                ' occupational therapy',
                'OCCUPATIONAL THERAPY',
            ])
        );
    }

    public function test_non_treatment_and_empty_categories_are_dropped(): void
    {
        $this->assertSame(
            ['psychiatric treatment'],
            GoalParentVisibility::uniqueTreatmentCategories(['educational', 'independent', '', null, 7, 'psychiatric treatment'])
        );
    }

    public function test_options_are_sorted_by_label(): void
    {
        $labels = ['physical therapy' => 'C', 'pronouncement' => 'A', 'occupational therapy' => 'B'];

        $options = GoalParentVisibility::treatmentCategoryOptions(
            ['physical therapy', 'pronouncement', 'occupational therapy'],
            fn (string $category) => $labels[$category]
        );

        $this->assertSame(['A', 'B', 'C'], array_column($options, 'title'));
        $this->assertSame(['pronouncement', 'occupational therapy', 'physical therapy'], array_column($options, 'value'));
    }

    public function test_every_treatment_category_has_arabic_and_english_label(): void
    {
        $expected = [
            'ar' => [
                'physical therapy' => 'العلاج الطبيعي',
                'occupational therapy' => 'العلاج الوظيفي',
                'pronouncement' => 'النطق والتخاطب',
                'psychiatric treatment' => 'العلاج النفسي',
            ],
            'en' => [
                'physical therapy' => 'Physical Therapy',
                'occupational therapy' => 'Occupational Therapy',
                'pronouncement' => 'Speech and Language Therapy',
                'psychiatric treatment' => 'Psychological Therapy',
            ],
        ];

        foreach ($expected as $locale => $labels) {
            app()->setLocale($locale);
            foreach (GoalParentVisibility::TREATMENT_CATEGORIES as $category) {
                $label = GoalParentVisibility::categoryLabel($category);
                $this->assertSame($labels[$category], $label, "{$locale}: {$category}");
                $this->assertStringNotContainsString('tr.', $label);
            }
        }
    }

    public function test_unknown_category_label_never_exposes_translation_key(): void
    {
        $this->assertSame('unknown therapy', GoalParentVisibility::categoryLabel('unknown therapy'));
    }
}
