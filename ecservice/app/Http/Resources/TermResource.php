<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TermResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'center_id' => $this->center_id,
            'value' => $this->id, //for select items VUEJS
            'title' => $this->name,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'first_evaluation_at' => $this->first_evaluation_at,
            'second_evaluation_at' => $this->second_evaluation_at,
            'third_evaluation_at' => $this->third_evaluation_at,
            'final_evaluation_at' => $this->final_evaluation_at,
            'periods_count' => $this->periodsCount(),
            'evaluation_dates' => $this->evaluationDates(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'assign_all_users' => $this->assignsAllUsers(),
            'user_ids' => $this->when(
                $this->relationLoaded('users'),
                fn () => $this->resolveAssignedUsers()->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            ),
            'users' => $this->when(
                $this->relationLoaded('users'),
                fn () => $this->resolveAssignedUsers()->map(function ($user) {
                    $roles = $user->relationLoaded('roles') ? $user->roles : collect();
                    $roleName = $roles->pluck('name')->filter()->first();
                    $jobTitle = $user->job_title ?? null;

                    return [
                        'id' => $user->id,
                        'name' => $user->name,
                        'job_title' => $jobTitle,
                        'roles' => $roles->map(fn ($role) => [
                            'id' => $role->id,
                            'name' => $role->name,
                            'default_name' => $role->default_name ?? null,
                        ])->values()->all(),
                        'title' => trim($user->name.($jobTitle || $roleName ? ' — '.($jobTitle ?: $roleName) : '')),
                    ];
                })->values()->all()
            ),
        ];
    }
}
