<?php

namespace App\Actions;

use App\Models\Patient;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class SearchPatientsAction
{
    public function execute(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Patient::query()->with(['demographics', 'addresses', 'identities.card']);

        // Apply search filters using the scopes defined in the Patient model
        $query = $this->applyFilters($query, $filters);

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    private function applyFilters(Builder $query, array $filters): Builder
    {
        // Search by patient code
        if (!empty($filters['code'])) {
            $query->byCode($filters['code']);
        }

        // Search by demographics
        if (!empty($filters['demographics'])) {
            $query->byDemographics($filters['demographics']);
        }

        // Search by identity code
        if (!empty($filters['identity_code'])) {
            $query->byIdentityCode($filters['identity_code']);
        }

        // Search by address
        if (!empty($filters['address'])) {
            $query->byAddress($filters['address']);
        }

        // Filter by facility
        if (!empty($filters['facility_id'])) {
            $query->where('facility_id', $filters['facility_id']);
        }

        // Filter by active insurance status
        if (isset($filters['has_active_insurance'])) {
            if ($filters['has_active_insurance']) {
                $query->whereHas('identities', function ($q) {
                    $q->active()->whereHas('card', function ($cardQuery) {
                        $cardQuery->active();
                    });
                });
            } else {
                $query->whereDoesntHave('identities', function ($q) {
                    $q->active()->whereHas('card', function ($cardQuery) {
                        $cardQuery->active();
                    });
                });
            }
        }

        // Filter by sex
        if (!empty($filters['sex'])) {
            $query->whereHas('demographics', function ($q) use ($filters) {
                $q->where('sex', $filters['sex']);
            });
        }

        // Filter by age range
        if (!empty($filters['age_min']) || !empty($filters['age_max'])) {
            $query->whereHas('demographics', function ($q) use ($filters) {
                if (!empty($filters['age_min'])) {
                    $q->whereDate('birthdate', '<=', now()->subYears($filters['age_min'])->toDateString());
                }
                if (!empty($filters['age_max'])) {
                    $q->whereDate('birthdate', '>=', now()->subYears($filters['age_max'])->toDateString());
                }
            });
        }

        // Filter by deceased status
        if (isset($filters['is_deceased'])) {
            $query->whereHas('demographics', function ($q) use ($filters) {
                if ($filters['is_deceased']) {
                    $q->whereNotNull('died_at');
                } else {
                    $q->whereNull('died_at');
                }
            });
        }

        return $query;
    }
}