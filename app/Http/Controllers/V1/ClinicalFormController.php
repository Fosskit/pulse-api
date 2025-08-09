<?php

namespace App\Http\Controllers\V1;

use App\Http\Requests\ClinicalForm\{StoreClinicalFormRequest, UpdateClinicalFormRequest};
use App\Http\Resources\ClinicalFormResource;
use App\Models\ClinicalFormTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\{AllowedFilter, QueryBuilder};

class ClinicalFormController extends BaseController
{
    public function index(Request $request)
    {
        $forms = QueryBuilder::for(ClinicalFormTemplate::class)
            ->allowedFilters([
                'title', 'category',
                AllowedFilter::exact('active'),
                AllowedFilter::scope('popular'),
                AllowedFilter::scope('recent')
            ])
            ->allowedSorts(['title', 'category', 'created_at', 'usage_count'])
            ->withCount('encounters')
            ->defaultSort('title')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse($forms, 'Clinical forms retrieved successfully');
    }

    public function store(StoreClinicalFormRequest $request)
    {
        try {
            $formData = $request->validated();
            $formData['created_by'] = auth()->id();
            $formData['updated_by'] = auth()->id();

            // Validate form schema structure
            $this->validateFormSchema($formData['form_schema']);

            $form = ClinicalFormTemplate::create($formData);

            return $this->successResponse(
                new ClinicalFormResource($form),
                'Clinical form created successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create clinical form: ' . $e->getMessage(), 500);
        }
    }

    public function show(ClinicalFormTemplate $clinicalForm)
    {
        $clinicalForm->loadCount('encounters');

        return $this->successResponse(
            new ClinicalFormResource($clinicalForm),
            'Clinical form retrieved successfully'
        );
    }

    public function update(UpdateClinicalFormRequest $request, ClinicalFormTemplate $clinicalForm)
    {
        try {
            $formData = $request->validated();
            $formData['updated_by'] = auth()->id();

            // Validate form schema structure if provided
            if (isset($formData['form_schema'])) {
                $this->validateFormSchema($formData['form_schema']);
            }

            $clinicalForm->update($formData);

            return $this->successResponse(
                new ClinicalFormResource($clinicalForm),
                'Clinical form updated successfully'
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update clinical form: ' . $e->getMessage(), 500);
        }
    }

    public function destroy(ClinicalFormTemplate $clinicalForm)
    {
        try {
            // Check if form is being used
            if ($clinicalForm->encounters()->exists()) {
                return $this->errorResponse(
                    'Cannot delete form that has been used in encounters. Consider deactivating instead.',
                    422
                );
            }

            $clinicalForm->delete();

            return $this->successResponse(null, 'Clinical form deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete clinical form: ' . $e->getMessage(), 500);
        }
    }

    public function preview(ClinicalFormTemplate $clinicalForm)
    {
        return $this->successResponse([
            'form' => new ClinicalFormResource($clinicalForm),
            'field_count' => $this->countFormFields($clinicalForm->form_schema),
            'estimated_time' => $this->estimateCompletionTime($clinicalForm->form_schema),
            'preview_data' => $this->generatePreviewData($clinicalForm->form_schema)
        ], 'Form preview generated');
    }

    public function duplicate(ClinicalFormTemplate $clinicalForm)
    {
        try {
            $duplicate = $clinicalForm->replicate();
            $duplicate->title = $clinicalForm->title . ' (Copy)';
            $duplicate->active = false;
            $duplicate->created_by = auth()->id();
            $duplicate->updated_by = auth()->id();
            $duplicate->save();

            return $this->successResponse(
                new ClinicalFormResource($duplicate),
                'Clinical form duplicated successfully',
                201
            );

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to duplicate clinical form: ' . $e->getMessage(), 500);
        }
    }

    public function activate(ClinicalFormTemplate $clinicalForm)
    {
        $clinicalForm->update([
            'active' => true,
            'updated_by' => auth()->id()
        ]);

        return $this->successResponse(
            new ClinicalFormResource($clinicalForm),
            'Clinical form activated successfully'
        );
    }

    public function deactivate(ClinicalFormTemplate $clinicalForm)
    {
        $clinicalForm->update([
            'active' => false,
            'updated_by' => auth()->id()
        ]);

        return $this->successResponse(
            new ClinicalFormResource($clinicalForm),
            'Clinical form deactivated successfully'
        );
    }

    public function statistics()
    {
        $stats = [
            'total_forms' => ClinicalFormTemplate::count(),
            'active_forms' => ClinicalFormTemplate::where('active', true)->count(),
            'by_category' => ClinicalFormTemplate::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get(),
            'most_used' => ClinicalFormTemplate::withCount('encounters')
                ->orderBy('encounters_count', 'desc')
                ->take(5)
                ->get(),
            'recent_usage' => $this->getRecentUsage(),
        ];

        return $this->successResponse($stats, 'Clinical form statistics retrieved');
    }

    // Private helper methods
    private function validateFormSchema(array $schema): void
    {
        if (!isset($schema['sections']) || !is_array($schema['sections'])) {
            throw new \InvalidArgumentException('Form schema must contain sections array');
        }

        foreach ($schema['sections'] as $section) {
            if (!isset($section['fields']) || !is_array($section['fields'])) {
                throw new \InvalidArgumentException('Each section must contain fields array');
            }

            foreach ($section['fields'] as $field) {
                if (!isset($field['type']) || !isset($field['id'])) {
                    throw new \InvalidArgumentException('Each field must have type and id');
                }
            }
        }
    }

    private function countFormFields(array $schema): int
    {
        $count = 0;
        foreach ($schema['sections'] ?? [] as $section) {
            $count += count($section['fields'] ?? []);
        }
        return $count;
    }

    private function estimateCompletionTime(array $schema): string
    {
        $fieldCount = $this->countFormFields($schema);
        $estimatedMinutes = max(2, ceil($fieldCount * 0.5)); // 30 seconds per field minimum 2 minutes
        return $estimatedMinutes . ' minutes';
    }

    private function generatePreviewData(array $schema): array
    {
        $preview = [];
        foreach ($schema['sections'] ?? [] as $sectionIndex => $section) {
            $preview["section_{$sectionIndex}"] = [
                'title' => $section['title'] ?? "Section " . ($sectionIndex + 1),
                'field_count' => count($section['fields'] ?? []),
                'columns' => $section['columns'] ?? 1
            ];
        }
        return $preview;
    }

    private function getRecentUsage(): array
    {
        return DB::table('encounters')
            ->join('clinical_form_templates', 'encounters.clinical_form_template_id', '=', 'clinical_form_templates.id')
            ->selectRaw('clinical_form_templates.title, DATE(encounters.created_at) as date, COUNT(*) as usage_count')
            ->where('encounters.created_at', '>=', now()->subDays(7))
            ->groupBy('clinical_form_templates.title', 'date')
            ->orderBy('date', 'desc')
            ->get()
            ->toArray();
    }
}
