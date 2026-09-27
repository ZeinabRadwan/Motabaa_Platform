<?php

namespace App\Http\Resources\Admin\User;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\User;
use App\Http\Resources\CenterResource;
use App\Http\Resources\Concerns\ListResourceMode;

class UserResource extends JsonResource
{
    use ListResourceMode;

    protected $includePermissions = false;

    public function __construct($resource, $includePermissions = false)
    {
        parent::__construct($resource);
        $this->includePermissions = $includePermissions === true;
    }

    public static function forSession($user)
    {
        $user->loadMissing(['roles.permissions', 'permissions', 'centers']);

        return new static($user, true);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        if ($this->listOnly) {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'email' => $this->email,
                'phone' => $this->phone,
                'picture' => $this->urlImage(),
                'job_title' => $this->job_title,
                'department' => $this->department,
                'department_label' => $this->department ? (User::departments()[$this->department] ?? $this->department) : null,
                'work_shift' => $this->work_shift,
                'work_shift_values' => $this->workShiftValues(),
                'work_shift_label' => $this->workShiftLabel(),
                'can_login' => $this->status == User::STATUS_CAN_LOGIN,
                'scaseParent' => $this->whenLoaded('scaseParent', function () {
                    return $this->scaseParent->map(fn ($scase) => [
                        'id' => $scase->id,
                        'name' => $scase->name,
                    ])->values();
                }),
                'roles' => collect($this->roles)->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'default_name' => $role->default_name ?? null,
                ])->values(),
                'centers' => collect($this->centers)->map(fn ($center) => [
                    'id' => $center->id,
                    'title' => $center->getNameAttribute(),
                ])->values(),
                'deleted_at' => $this->deleted_at,
            ];
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified_at' => $this->email_verified_at,
            'picture' => $this->urlImage(),
            'birthdate' => $this->birthdate,
            'id_or_residence_number' => $this->id_or_residence_number,
            'gender' => $this->gender != 0 ? $this->gender : null,
            'gender_type' => isset($this->genderTypes()[$this->gender]) ? $this->genderTypes()[$this->gender] : '',
            'address_building' => $this->address_building,
            'address_street' => $this->address_street,
            'address_area' => $this->address_area,
            'address_city' => $this->address_city,
            'address_zipcode' => $this->address_zipcode,
            'address_number' => $this->address_number,
            'address_unit' => $this->address_unit,
            'can_login' => $this->status == User::STATUS_CAN_LOGIN,
            'on_center_sponsorship' => $this->on_center_sponsorship,
            'qualification' => $this->qualification,
            'specialization' => $this->specialization,
            'precise_specialization' => $this->precise_specialization,
            'current_work' => $this->current_work,
            'nationality' => $this->nationality,
            'job_title' => $this->job_title,
            'department' => $this->department,
            'department_label' => $this->department ? (User::departments()[$this->department] ?? $this->department) : null,
            'work_shift' => $this->work_shift,
            'work_shift_values' => $this->workShiftValues(),
            'work_shift_label' => $this->workShiftLabel(),
            'contract_type' => $this->contract_type,
            'contract_type_label' => $this->contract_type ? (User::contractTypes()[$this->contract_type] ?? $this->contract_type) : null,
            'hire_date' => $this->hire_date,
            'contract_end_date' => $this->contract_end_date,
            'id_expiry_date' => $this->id_expiry_date,
            'annual_leave_entitlement' => $this->annualLeaveEntitlement(),
            'has_password' => $this->password != null,
            'scaseParent'=> $this->whenLoaded('scaseParent'),
            'roles' => collect($this->roles)->map(fn ($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'default_name' => $role->default_name ?? null,
            ])->values(),
            'is_parent_user' => $this->when(
                $this->includePermissions,
                fn () => isParentUser($this->resource)
            ),
            'centers' => CenterResource::collection($this->centers),
            'permissions' => $this->when($this->includePermissions, fn () => $this->getAllPermissions()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
        ];
    }
}
