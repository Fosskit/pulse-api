<?php

namespace Database\Seeders;

use App\Models\Terminology;
use App\Models\TaxonomyValue;
use Illuminate\Database\Seeder;

class TaxonomySeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed base taxonomy terms structure
        $this->seedTaxonomyTerms();

        // 2. Seed individual taxonomy values
        $this->seedDemographicTaxonomies();
        $this->seedClinicalTaxonomies();
        $this->seedAdministrativeTaxonomies();
    }

    private function seedTaxonomyTerms(): void
    {
        $taxonomies = [
            // Main categories
            [
                'code' => 'demographic',
                'name' => 'Demographic',
                'name_kh' => 'ព័ត៌មានប្រជាសាស្ត្រ',
                'description' => 'Patient demographic information categories',
                'sort_order' => 1,
            ],
            [
                'code' => 'clinical',
                'name' => 'Clinical',
                'name_kh' => 'ព័ត៌មានគ្លីនិក',
                'description' => 'Clinical information categories',
                'sort_order' => 2,
            ],
            [
                'code' => 'administrative',
                'name' => 'Administrative',
                'name_kh' => 'ព័ត៌មានរដ្ឋបាល',
                'description' => 'Administrative information categories',
                'sort_order' => 3,
            ],

            // Demographic subcategories
            [
                'code' => 'marital_status',
                'name' => 'Marital Status',
                'name_kh' => 'ស្ថានភាពអាពាហ៍ពិពាហ៍',
                'parent_code' => 'demographic',
                'sort_order' => 1,
            ],
            [
                'code' => 'relationship',
                'name' => 'Relationships',
                'name_kh' => 'ទំនាក់ទំនង',
                'parent_code' => 'demographic',
                'sort_order' => 2,
            ],

            // Relationship subcategories
            [
                'code' => 'immediate_family',
                'name' => 'Immediate Family',
                'name_kh' => 'គ្រួសារផ្ទាល់',
                'parent_code' => 'relationship',
                'sort_order' => 1,
            ],
            [
                'code' => 'extended_family',
                'name' => 'Extended Family',
                'name_kh' => 'សាច់ញាតិ',
                'parent_code' => 'relationship',
                'sort_order' => 2,
            ],
            [
                'code' => 'in_laws',
                'name' => 'In-Laws',
                'name_kh' => 'សាច់ញាតិឆ្លង',
                'parent_code' => 'relationship',
                'sort_order' => 3,
            ],
            [
                'code' => 'step_family',
                'name' => 'Step Family',
                'name_kh' => 'គ្រួសារចុង',
                'parent_code' => 'relationship',
                'sort_order' => 4,
            ],
            [
                'code' => 'non_familial',
                'name' => 'Non-Familial',
                'name_kh' => 'មិនមែនសាច់ញាតិ',
                'parent_code' => 'relationship',
                'sort_order' => 5,
            ],

            // Clinical subcategories
            [
                'code' => 'allergen_type',
                'name' => 'Allergen Types',
                'name_kh' => 'ប្រភេទនៃអាឡែហ្ស៊ី',
                'parent_code' => 'clinical',
                'sort_order' => 1,
            ],
            [
                'code' => 'severity',
                'name' => 'Severity Levels',
                'name_kh' => 'កម្រិតភាពធ្ងន់ធ្ងរ',
                'parent_code' => 'clinical',
                'sort_order' => 2,
            ],
            [
                'code' => 'condition_status',
                'name' => 'Condition Statuses',
                'name_kh' => 'ស្ថានភាពរោគ',
                'parent_code' => 'clinical',
                'sort_order' => 3,
            ],

            // Administrative subcategories
            [
                'code' => 'history_action_type',
                'name' => 'History Action Types',
                'name_kh' => 'ប្រភេទសកម្មភាពប្រវត្តិ',
                'parent_code' => 'administrative',
                'sort_order' => 1,
            ],
        ];

        // Create taxonomies with parent-child relationships
        foreach ($taxonomies as $taxonomy) {
            $parentCode = $taxonomy['parent_code'] ?? null;

            // Remove parent_code from array
            if (isset($taxonomy['parent_code'])) {
                unset($taxonomy['parent_code']);
            }

            // If it has a parent, find the parent ID
            if ($parentCode) {
                $parent = Terminology::where('code', $parentCode)->first();
                if ($parent) {
                    $taxonomy['parent_id'] = $parent->id;
                }
            }

            Terminology::create($taxonomy);
        }
    }

    private function seedDemographicTaxonomies(): void
    {
        // Marital Status
        $this->seedTaxonomyValues('marital_status', [
            ['code' => 'single', 'name' => 'Single', 'name_kh' => 'នៅលីវ', 'sort_order' => 1],
            ['code' => 'married', 'name' => 'Married', 'name_kh' => 'រៀបការ', 'sort_order' => 2],
            ['code' => 'divorced', 'name' => 'Divorced', 'name_kh' => 'លែងលះ', 'sort_order' => 3],
            ['code' => 'widowed', 'name' => 'Widowed', 'name_kh' => 'មេម៉ាយ/ពោះម៉ាយ', 'sort_order' => 4],
        ]);

        // Immediate Family
        $this->seedTaxonomyValues('immediate_family', [
            ['code' => 'spouse', 'name' => 'Spouse', 'name_kh' => 'ប្តី/ប្រពន្ធ', 'sort_order' => 1],
            ['code' => 'father', 'name' => 'Father', 'name_kh' => 'ឪពុក', 'sort_order' => 2],
            ['code' => 'mother', 'name' => 'Mother', 'name_kh' => 'ម្តាយ', 'sort_order' => 3],
            ['code' => 'son', 'name' => 'Son', 'name_kh' => 'កូនប្រុស', 'sort_order' => 4],
            ['code' => 'daughter', 'name' => 'Daughter', 'name_kh' => 'កូនស្រី', 'sort_order' => 5],
            ['code' => 'brother', 'name' => 'Brother', 'name_kh' => 'បងប្អូនប្រុស', 'sort_order' => 6],
            ['code' => 'sister', 'name' => 'Sister', 'name_kh' => 'បងប្អូនស្រី', 'sort_order' => 7],
            ['code' => 'older_brother', 'name' => 'Older Brother', 'name_kh' => 'បងប្រុស', 'sort_order' => 8],
            ['code' => 'older_sister', 'name' => 'Older Sister', 'name_kh' => 'បងស្រី', 'sort_order' => 9],
            ['code' => 'younger_brother', 'name' => 'Younger Brother', 'name_kh' => 'ប្អូនប្រុស', 'sort_order' => 10],
            ['code' => 'younger_sister', 'name' => 'Younger Sister', 'name_kh' => 'ប្អូនស្រី', 'sort_order' => 11],
        ]);

        // Extended Family
        $this->seedTaxonomyValues('extended_family', [
            ['code' => 'grandfather_paternal', 'name' => 'Paternal Grandfather', 'name_kh' => 'តា', 'sort_order' => 1],
            ['code' => 'grandmother_paternal', 'name' => 'Paternal Grandmother', 'name_kh' => 'យាយ', 'sort_order' => 2],
            ['code' => 'grandfather_maternal', 'name' => 'Maternal Grandfather', 'name_kh' => 'លោកតា', 'sort_order' => 3],
            ['code' => 'grandmother_maternal', 'name' => 'Maternal Grandmother', 'name_kh' => 'លោកយាយ', 'sort_order' => 4],
            ['code' => 'grandson', 'name' => 'Grandson', 'name_kh' => 'ចៅប្រុស', 'sort_order' => 5],
            ['code' => 'granddaughter', 'name' => 'Granddaughter', 'name_kh' => 'ចៅស្រី', 'sort_order' => 6],
            ['code' => 'uncle_paternal', 'name' => 'Paternal Uncle', 'name_kh' => 'ពូ', 'sort_order' => 7],
            ['code' => 'aunt_paternal', 'name' => 'Paternal Aunt', 'name_kh' => 'មីង', 'sort_order' => 8],
            ['code' => 'uncle_maternal', 'name' => 'Maternal Uncle', 'name_kh' => 'មា', 'sort_order' => 9],
            ['code' => 'aunt_maternal', 'name' => 'Maternal Aunt', 'name_kh' => 'មុំ', 'sort_order' => 10],
            ['code' => 'nephew', 'name' => 'Nephew', 'name_kh' => 'កូនប្រុសបងប្អូន', 'sort_order' => 11],
            ['code' => 'niece', 'name' => 'Niece', 'name_kh' => 'កូនស្រីបងប្អូន', 'sort_order' => 12],
            ['code' => 'cousin', 'name' => 'Cousin', 'name_kh' => 'កូនពូមីង', 'sort_order' => 13],
        ]);

        // In-Laws
        $this->seedTaxonomyValues('in_laws', [
            ['code' => 'father_in_law', 'name' => 'Father-in-law', 'name_kh' => 'ឪពុកក្មេក', 'sort_order' => 1],
            ['code' => 'mother_in_law', 'name' => 'Mother-in-law', 'name_kh' => 'ម្តាយក្មេក', 'sort_order' => 2],
            ['code' => 'son_in_law', 'name' => 'Son-in-law', 'name_kh' => 'កូនប្រសារប្រុស', 'sort_order' => 3],
            ['code' => 'daughter_in_law', 'name' => 'Daughter-in-law', 'name_kh' => 'កូនប្រសារស្រី', 'sort_order' => 4],
            ['code' => 'brother_in_law', 'name' => 'Brother-in-law', 'name_kh' => 'ប្អូនថ្លៃ/បងថ្លៃ', 'sort_order' => 5],
            ['code' => 'sister_in_law', 'name' => 'Sister-in-law', 'name_kh' => 'ប្អូនថ្លៃ/បងថ្លៃ', 'sort_order' => 6],
        ]);

        // Step Family
        $this->seedTaxonomyValues('step_family', [
            ['code' => 'stepfather', 'name' => 'Stepfather', 'name_kh' => 'ឪពុកចិញ្ចឹម', 'sort_order' => 1],
            ['code' => 'stepmother', 'name' => 'Stepmother', 'name_kh' => 'ម្តាយចិញ្ចឹម', 'sort_order' => 2],
            ['code' => 'stepson', 'name' => 'Stepson', 'name_kh' => 'កូនចិញ្ចឹមប្រុស', 'sort_order' => 3],
            ['code' => 'stepdaughter', 'name' => 'Stepdaughter', 'name_kh' => 'កូនចិញ្ចឹមស្រី', 'sort_order' => 4],
            ['code' => 'stepbrother', 'name' => 'Stepbrother', 'name_kh' => 'បងប្អូនប្រុសចិញ្ចឹម', 'sort_order' => 5],
            ['code' => 'stepsister', 'name' => 'Stepsister', 'name_kh' => 'បងប្អូនស្រីចិញ្ចឹម', 'sort_order' => 6],
        ]);

        // Non-Familial
        $this->seedTaxonomyValues('non_familial', [
            ['code' => 'friend', 'name' => 'Friend', 'name_kh' => 'មិត្តភក្តិ', 'sort_order' => 1],
            ['code' => 'caregiver', 'name' => 'Caregiver', 'name_kh' => 'អ្នកថែទាំ', 'sort_order' => 2],
            ['code' => 'guardian', 'name' => 'Guardian', 'name_kh' => 'អាណាព្យាបាល', 'sort_order' => 3],
            ['code' => 'neighbor', 'name' => 'Neighbor', 'name_kh' => 'អ្នកជិតខាង', 'sort_order' => 4],
            ['code' => 'colleague', 'name' => 'Colleague', 'name_kh' => 'សហការី', 'sort_order' => 5],
            ['code' => 'other', 'name' => 'Other', 'name_kh' => 'ផ្សេងៗ', 'sort_order' => 6],
        ]);
    }

    private function seedClinicalTaxonomies(): void
    {
        // Allergen Types
        $this->seedTaxonomyValues('allergen_type', [
            ['code' => 'medication', 'name' => 'Medication', 'name_kh' => 'ឱសថ', 'sort_order' => 1],
            ['code' => 'food', 'name' => 'Food', 'name_kh' => 'អាហារ', 'sort_order' => 2],
            ['code' => 'environmental', 'name' => 'Environmental', 'name_kh' => 'បរិស្ថាន', 'sort_order' => 3],
        ]);

        // Severity Levels
        $this->seedTaxonomyValues('severity', [
            ['code' => 'mild', 'name' => 'Mild', 'name_kh' => 'ស្រាល', 'sort_order' => 1],
            ['code' => 'moderate', 'name' => 'Moderate', 'name_kh' => 'មធ្យម', 'sort_order' => 2],
            ['code' => 'severe', 'name' => 'Severe', 'name_kh' => 'ធ្ងន់', 'sort_order' => 3],
        ]);

        // Condition Status
        $this->seedTaxonomyValues('condition_status', [
            ['code' => 'active', 'name' => 'Active', 'name_kh' => 'សកម្ម', 'sort_order' => 1],
            ['code' => 'resolved', 'name' => 'Resolved', 'name_kh' => 'បានដោះស្រាយ', 'sort_order' => 2],
            ['code' => 'chronic', 'name' => 'Chronic', 'name_kh' => 'រ៉ាំរ៉ៃ', 'sort_order' => 3],
            ['code' => 'remission', 'name' => 'Remission', 'name_kh' => 'ធូរស្បើយ', 'sort_order' => 4],
        ]);
    }

    private function seedAdministrativeTaxonomies(): void
    {
        // History Action Types
        $this->seedTaxonomyValues('history_action_type', [
            ['code' => 'create', 'name' => 'Create', 'name_kh' => 'បង្កើត', 'sort_order' => 1],
            ['code' => 'update', 'name' => 'Update', 'name_kh' => 'កែប្រែ', 'sort_order' => 2],
            ['code' => 'delete', 'name' => 'Delete', 'name_kh' => 'លុប', 'sort_order' => 3],
        ]);
    }

    /**
     * Helper method to seed taxonomy values
     */
    private function seedTaxonomyValues(string $taxonomyCode, array $values): void
    {
        $taxonomy = Terminology::where('code', $taxonomyCode)->first();

        if (!$taxonomy) {
            return;
        }

        foreach ($values as $value) {
            $value['taxonomy_id'] = $taxonomy->id;
            TaxonomyValue::create($value);
        }
    }
}
