<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\CenterUser;
use App\Models\Center;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\Admin\User\UserResource;
use App\Http\Resources\Admin\User\UserFileResource;
use App\Http\Requests\Users\PutRequest;
use App\Http\Requests\Users\ImpersonateRequest;
use App\Http\Requests\Users\RegisterRequest;
use App\Models\System\System;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use App\Models\Log;

use App\Models\System\ExportExcel;
use App\Exports\ExportEmployees;
use App\Exports\ExportParents;
use App\Mail\SystemMail;
use App\Models\UserFileMeta;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $perPage = resolvePerPage($request);

        $rolesSearch = $request->roles;
        $case_id = $request->case_id;
        $roleSearch = 'parent';
        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $user = auth()->user();
        $center = null;
        if($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($user->centers) {
            $center = $user->centers[0]->id;
        }

        $with = ['roles:id,name,default_name', 'centers:id,title,title_local'];
        if($request->onlyParents){
            $with[] = 'scaseParent:id,name';
        }
        $users = User::with($with)->whereHas('centers', function ($query) use ($center) {
            $query->where('centers.id', $center);
        })
        ->when(
            $keywords,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
        )
        ->when(
            $rolesSearch,
            fn ($q) => $q->whereHas('roles', function ($query) use ($rolesSearch) {
                $query->whereIn('roles.id', $rolesSearch);
            })
        )
        // ->when(
        //     $request->excludeParent && !$rolesSearch,
        //     fn ($q) => $q->whereHas('roles', function ($query) use ($roleSearch) {
        //         $query->where('default_name', '!=', 'parent');
        //     })
        // )
        ->when(
            $request->excludeParent,
            fn ($q) => $q->whereHas('roles', function ($query) {
                $query->where('roles.default_name', '!=','parent')
                ->orWhere('roles.default_name', '<>','parent')
                ->orWhereNull('roles.default_name');
            })
        )
        ->when(
            $case_id,
            fn ($q) => $q->whereHas('scaseParent', function ($query) use ($case_id) {
                $query->where('scases.id', $case_id);
            })
        )
        ->when(
            $request->onlyParents,
            fn ($q) => $q->whereHas('roles', function ($query) {
                $query->where('roles.default_name', 'parent');
            })
        )
        ->when(
            $request->status &&  $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status &&  $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->when(
            $request->department,
            fn ($q) => $q->where('department', $request->department)
        )
        ->when(
            $request->work_shift,
            fn ($q) => $q->matchingWorkShift($request->work_shift)
        )
        ->when(
            $request->contract_type,
            fn ($q) => $q->where('contract_type', $request->contract_type)
        )
        ->when(
            $request->hr_alert == 'contracts_ending',
            fn ($q) => $q->whereNotNull('contract_end_date')
                ->whereBetween('contract_end_date', [Carbon::now()->toDateString(), Carbon::now()->addDays(90)->toDateString()])
        )
        ->when(
            $request->hr_alert == 'ids_expiring',
            fn ($q) => $q->whereNotNull('id_expiry_date')
                ->whereBetween('id_expiry_date', [Carbon::now()->toDateString(), Carbon::now()->addDays(90)->toDateString()])
        )
        ->when(
            $request->hr_alert == 'documents_expiring',
            fn ($q) => $q->whereHas('fileMeta', function ($meta) {
                $meta->whereNotNull('expiry_date')
                    ->whereBetween('expiry_date', [Carbon::now()->toDateString(), Carbon::now()->addDays(90)->toDateString()]);
            })
        )
        ->when(
            $request->hr_alert == 'expiring_ids_or_docs',
            fn ($q) => $q->where(function ($query) {
                $from = Carbon::now()->toDateString();
                $to = Carbon::now()->addDays(90)->toDateString();
                $query->where(function ($idQ) use ($from, $to) {
                    $idQ->whereNotNull('id_expiry_date')
                        ->whereBetween('id_expiry_date', [$from, $to]);
                })->orWhereHas('fileMeta', function ($meta) use ($from, $to) {
                    $meta->whereNotNull('expiry_date')
                        ->whereBetween('expiry_date', [$from, $to]);
                });
            })
        );
        
        if($request->export == 'export_employees') {
            
            $users = $users->get();
            $fileName = ExportExcel::defaultFileName('Employees');
            $token = ExportExcel::saveExcel(new ExportEmployees($users), $fileName);
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else if($request->export == 'export_parents') {
            
            $users = $users->get();
            $fileName = ExportExcel::defaultFileName('Parents');
            $token = ExportExcel::saveExcel(new ExportParents($users), $fileName);
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else {

            $users = $users->paginate($perPage);

            return apiPaginateResponse($users, UserResource::listCollection($users));
        }
    }

    public function show(Request $request, $user)
    {
        $currentUser = auth()->user();
        if (!can('show_users') && !can('show_parents') && $currentUser->id != $user)
            return response()->json(['errors' => ['error' => [__("validation.You don't have permission to access this page.")]]], 422);

        $center = null;
        if($request->center_id && $currentUser->isInCenter($request->center_id)) {
            $center = $request->center_id;
        }
        else if($currentUser->centers){ 
            $center = $currentUser->centers[0]->id;
        }

        $userData = User::with(['roles', 'centers'])
        ->whereHas('centers', function ($query) use ($center) {
            $query->where('centers.id', $center);
        })
        ->withTrashed()
        ->find($user);

        if($userData)
            $userData = UserResource::forSession($userData);
        else
            $userData = null;

        return apiResponse($userData);
    }

    public function register(RegisterRequest $request) {

        $input = $request->validated();
        $input['status'] = User::STATUS_CANNT_LOGIN;
        if($input['password']){
            $input['password'] = bcrypt($input['password']);
        }

        $centerInput = array(
            'title'=> $request->title,
            'title_local'=> $request->title_local,
            'number_of_cases'=> $request->number_of_cases,
            'country'=> $request->country,
            'city'=> $request->city,
            'package_id'=> $request->package_id,
            'status'=> 0,
        );

        $user = User::create($input);
        $center = Center::create($centerInput);
        CenterUser::create(['center_id'=> $center->id, 'user_id'=> $user->id]);
        $user->syncRoles([3]);

        $token = \Illuminate\Support\Facades\Crypt::encryptString(json_encode([
            'id' => $user->id, 
            'email' => $user->email
        ]));
        $title = "رابط التفعيل";
        $systemMail = new SystemMail(['title'=> $title, 'verify_link' => route("verify_user", ['token'=> $token]), 'name'=> $request->name]);
        $systemMail->submit($request->email);

        return success();
    }

    public function verify($token) {

        $decryptedData = json_decode(\Illuminate\Support\Facades\Crypt::decryptString($token), true);
        $user = User::find($decryptedData['id']);
        if($user && $user->email == $decryptedData['email']) {
            $user->status = 1;
            $user->save();
        }

        return redirect()->away(config('app.front_url'));
    }

    public function create(PutRequest $request) {

        $input = $request->validated();
        if (!User::hasWorkShiftColumn()) {
            unset($input['work_shift']);
        }

        $currentUser = auth()->user();
        if($input['center_id'] && !$currentUser->isInCenter($input['center_id']))
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to this center")]]], 422);

        if($input['password']){
            $input['password'] = bcrypt($input['password']);
        }
        if($input['can_login'] && $input['can_login'] == 'hide'){
            $input['status'] = User::STATUS_CANNT_LOGIN;
        }

        $user = User::create($input);
        if($input['center_id']){
            CenterUser::create(['center_id'=> $input['center_id'], 'user_id'=> $user->id]);
        }

        if($input['roles']){
            $user->syncRoles( explode(',', $input['roles']));
        }

        if($request->hasFile('image')){
            $user->setImage($input['image']);
        }

        Log::add('add_user', $user, $user->id, '');
        return response()->json(['message' => '', 'status' => true], 200);
    }

    public function update(PutRequest $request, User $user) {

        $input = $request->validated();
        if (!User::hasWorkShiftColumn()) {
            unset($input['work_shift']);
        }
        if($input['can_login'] && $input['can_login'] == 'hide'){
            $input['status'] = User::STATUS_CANNT_LOGIN;
        }else if($input['can_login'] && $input['can_login'] == 'show'){
            $input['status'] = User::STATUS_CAN_LOGIN;
        }

        $user->on_center_sponsorship = isset($input['on_center_sponsorship']) ? $input['on_center_sponsorship'] : 0;
        $user->update($input);

        if($input['roles']){
            $user->syncRoles(explode(',', $input['roles']));
        }

        if($request->hasFile('image')){
            $currentImage = null;
            if($request->current_image)
                $currentImage = $request->current_image;
            $user->setImage($input['image'], $currentImage);
        }

        Log::add('edit_user', $user, $user->id, '');
        return response()->json(['message' => '', 'status' => true], 200);
    }

    public function change_password(PutRequest $request, User $user){
        $input = $request->validated();
        $user->update([
            'password' => bcrypt($input['password']),
        ]);
        $user->loadMissing('centers');
        Log::add('change_user_password', $user, $user->id, '', $user->centers[0]->id ?? 0);
        return response()->json(['message' => '', 'status' => true], 200);
    }

    public function impersonate(ImpersonateRequest $request, User $user)
    {
        $admin = $request->user();

        if (isImpersonating($admin) || $user->id === $admin->id || isHasRole('admin', $user)) {
            return response()->json(['errors' => ['error' => [__("validation.You cannot impersonate this user.")]]], 422);
        }

        if ($user->trashed() || $user->status != User::STATUS_CAN_LOGIN) {
            return response()->json(['errors' => ['error' => [__("validation.This user cannot log in.")]]], 422);
        }

        $user->load(['roles', 'centers']);
        $startedAt = now();
        $token = $user->createToken('impersonation:'.$admin->id.':'.$startedAt->timestamp)->plainTextToken;

        $description = sprintf(
            'Admin #%d %s (%s) started impersonating User #%d %s (%s) at %s',
            $admin->id,
            $admin->name,
            $admin->email,
            $user->id,
            $user->name,
            $user->email,
            $startedAt->toDateTimeString()
        );

        Log::add('impersonate_start', $user, $user->id, $description, $user->centers[0]->id ?? 0);

        return response()->json([
            'user' => UserResource::forSession($user),
            'token' => $token,
            'status' => true,
        ]);
    }

    public function stop_impersonation(Request $request)
    {
        $token = $request->user()?->currentAccessToken();
        if (!$token || !str_starts_with((string) $token->name, 'impersonation:')) {
            return response()->json(['errors' => ['error' => [__("validation.You can only stop an impersonation session.")]]], 422);
        }

        $parts = explode(':', (string) $token->name);
        $adminId = (int) ($parts[1] ?? 0);
        $startedTs = (int) ($parts[2] ?? 0);
        $target = $request->user();
        $admin = $adminId ? User::withTrashed()->find($adminId) : null;
        $endedAt = now();
        $startedAt = $startedTs ? Carbon::createFromTimestamp($startedTs) : null;

        $description = sprintf(
            'Admin #%d %s (%s) returned from impersonating User #%d %s (%s). Started at %s. Ended at %s',
            $adminId,
            $admin ? $admin->name : '',
            $admin ? $admin->email : '',
            $target->id,
            $target->name,
            $target->email,
            $startedAt ? $startedAt->toDateTimeString() : 'unknown',
            $endedAt->toDateTimeString()
        );

        $target->loadMissing('centers');
        Log::add('impersonate_stop', $target, $target->id, $description, $target->centers[0]->id ?? 0, $adminId ?: null);

        $adminToken = null;
        $adminResource = null;
        if ($admin) {
            $admin->load(['roles', 'centers']);
            $adminToken = $admin->createToken('auth-token')->plainTextToken;
            $adminResource = UserResource::forSession($admin);
        }

        $token->delete();

        return response()->json([
            'status' => true,
            'token' => $adminToken,
            'user' => $adminResource,
        ]);
    }

    public function delete($id)
    {
        $record = User::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_user', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restore($id)
    {
        $record = User::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_user', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function fetchImage(Request $request, $id) {

        if($request->attendance_at) {

            $user = User::find($id);
            $fileName = "absent_file_{$request->attendance_at}";
            $fileURL = $user->urlAttendanceFile($fileName);
            if($fileURL)
                return apiResponse($fileURL);
        }
        else if($request->image && $request->image == 'center_logo') {

            $center = Center::find($id);
            $fileURL = $center->urlLogo();
            if($fileURL)
                return apiResponse($fileURL);
        }
        else {

            $user = User::find($id);
            $imageURL = $user->urlImage();
            if($imageURL)
                return apiResponse($imageURL);
        }

        return apiResponse(null);
    }

    public function deleteImage($id)
    {
        $record = User::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->deleteImage();
        Log::add('delete_user_image', $record, $record->id, '');
        return response()->json(['message' => 'Image deleted successfully', 'status' => true]);
    }

    protected function autocompleteUsers(Request $request)
    {
        $autocomplete = parseAutocompleteRequest($request);
        $preload = $request->boolean('preload');
        if ($preload) {
            $autocomplete['run'] = true;
        }
        if (!$autocomplete['run']) {
            return collect();
        }

        $currentUser = auth()->user();
        $center = null;
        if ($request->center_id && ($currentUser->isInCenter($request->center_id) || isHasRole('admin', $currentUser))) {
            $center = $request->center_id;
        } else if ($currentUser->centers) {
            $center = $currentUser->centers[0]->id;
        }

        $roles = $request->roles;
        if (is_string($roles) && $roles !== 'all') {
            $roles = array_filter(explode(',', $roles));
        }

        $query = User::select('users.id', 'users.name', 'users.job_title')
            ->with('roles:id,name,default_name')
            ->where(function ($subQuery) use ($center, $request) {
                $subQuery->whereHas('centers', function ($centerQuery) use ($center) {
                    $centerQuery->where('centers.id', $center);
                });
                if (!$request->boolean('center_staff_only')) {
                    $subQuery->orWhereHas('roles', function ($roleQuery) {
                        $roleQuery->where('roles.default_name', 'admin');
                    });
                }
            })
            ->when(
                $autocomplete['keywords'] && $autocomplete['ids'],
                fn ($q) => $q->where(function ($inner) use ($autocomplete) {
                    $inner->where('search_text', 'like', "%{$autocomplete['keywords']}%")
                        ->orWhereIn('users.id', $autocomplete['ids']);
                })
            )
            ->when(
                $autocomplete['keywords'] && !$autocomplete['ids'],
                fn ($q) => $q->where('search_text', 'like', "%{$autocomplete['keywords']}%")
            )
            ->when(
                !$autocomplete['keywords'] && $autocomplete['ids'],
                fn ($q) => $q->whereIn('users.id', $autocomplete['ids'])
            )
            ->when(
                $roles && $roles !== 'all',
                fn ($q) => $q->whereHas('roles', function ($roleQuery) use ($roles) {
                    $roleQuery->whereIn('default_name', (array) $roles);
                })
            )
            ->when(
                $request->excludeParent,
                fn ($q) => $q->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('roles.default_name', '!=', 'parent')
                        ->orWhereNull('roles.default_name');
                })
            )
            ->when(
                $request->boolean('center_staff_only'),
                fn ($q) => $q->where('users.status', User::STATUS_CAN_LOGIN)
            )
            ->when(
                $request->onlyParents,
                fn ($q) => $q->whereHas('roles', function ($roleQuery) {
                    $roleQuery->where('roles.default_name', 'parent');
                })
            )
            ->when(
                $request->status && $request->status == 'all',
                fn ($q) => $q->withTrashed()
            )
            ->when(
                $request->status && $request->status == 'inactive',
                fn ($q) => $q->onlyTrashed()
            );

        if (isHasRole(System::USER_TYPE_PARENT_ROLE_NAME)) {
            $scases = auth()->user()->scaseParent()->pluck('scases.id')->toArray();
            $query->join('scase_user', function ($join) use ($scases) {
                $join->on('scase_user.user_id', 'users.id');
                $join->whereIn('scase_user.scase_id', $scases);
            });
        }

        $limit = $autocomplete['limit'];
        if ($preload) {
            $requestedLimit = (int) $request->get('limit', 500);
            $limit = min(max($requestedLimit, 1), 500);
        }

        return $query->orderBy('users.name')
            ->limit($limit)
            ->get();
    }

    public function selectItems(Request $request)
    {
        $usersByRoles = $this->autocompleteUsers($request);

        if ($request->roles != 'all' && !$request->not_grouped) {
            $usersByRoles->load('roles:id,default_name');
            $usersByRoles = $usersByRoles->groupBy(function ($user) {
                return $user->roles->pluck('default_name')->unique()->toArray();
            });
        }

        return apiResponse($usersByRoles);
    }

    public function searchItems(Request $request)
    {
        return apiResponse($this->autocompleteUsers($request));
    }

    public function fetchRolesUsers(Request $request)
    {
        if($request->fetch == 'fetch_roles_users') {

            $usersByRoles = User::select('id', 'name')
            ->with('roles:id,name')
            ->whereHas('centers', function ($query) use ($request) {
                $query->where('centers.id', $request->center_id);
            })
            ->when(
                $request->roles,
                fn ($q) => $q->whereHas('roles', function ($query) use ($request) {
                    $query->whereIn('default_name', $request->roles);
                })
            )
            ->when(
                $request->exclude_roles,
                fn ($q) => $q->whereHas('roles', function ($query) use ($request) {
                    $query->whereNotIn('default_name', $request->exclude_roles);
                })
            );

            $usersByRoles = $usersByRoles->get()
            ->groupBy(function ($user) {
                return $user->roles->pluck('name')->toArray();
            });
            
            return apiResponse($usersByRoles);
        }
        else if($request->fetch == 'fetch_employees') {

            $employees = User::select(
            'id', 
            'name', 
            'nationality', 
            'id_or_residence_number', 
            'phone', 
            'on_center_sponsorship', 
            'qualification', 
            'specialization', 
            'precise_specialization', 
            'current_work',
            'job_title',
            'department',
            'contract_type',
            'hire_date',
            'contract_end_date',
            'id_expiry_date'
            )
            ->whereHas('centers', function ($query) use ($request) {
                $query->where('centers.id', $request->center_id);
            })
            ->whereHas('roles', function ($query) {
                $query->whereNotIn('default_name', ['admin', 'parent']);
            })
            ->with('roles:id,name')
            ->orderBy('id', 'ASC')
            ->get();
            
            return apiResponse($employees);
        }
        else if($request->fetch == 'fetch_planning_team') {
            
            $planningTeam = User::select('id', 'name', 'nationality')
            ->with('roles:id,name')
            ->whereHas('centers', function ($query) use ($request) {
                $query->where('centers.id', $request->center_id);
            })
            ->whereHas('roles', function ($query) {
                $query->where('default_name', 'like', '%planning%');
            })
            ->orderBy('id', 'ASC');
    
            $planningTeam = $planningTeam->get();
            
            return apiResponse($planningTeam);
        }
        else if($request->fetch == 'fetch_users_count') {
            
            $nUsers = User::selectRaw('roles.name, count(users.id) as users_count')
            ->join('model_has_roles', function($join) {
                $join->on('model_has_roles.model_id', '=', 'users.id');
                $join->where('model_type', 'App\Models\User');
            })
            ->join('roles', 'roles.id', 'model_has_roles.role_id')
            ->whereHas('centers', function ($query) use ($request) {
                $query->where('centers.id', $request->center_id);
            })
            ->when(
                $request->roles,
                fn ($q) => $q->whereIn('default_name', $request->roles)
            )
            ->when(
                $request->exclude_roles,
                fn ($q) => $q->whereNotIn('default_name', $request->exclude_roles)
            )
            ->when(
                $request->nationality,
                fn ($q) => $q->where('nationality', $request->nationality)
            );

            if($request->like_roles) {
                $nUsers->where(function ($query) use ($request) {
                    foreach ($request->like_roles as $role) {
                        $query->orWhere('default_name', 'like', "%{$role}%");
                    }
                });
            }

            if($request->not_like_roles) {
                foreach ($request->not_like_roles as $role) {
                    $nUsers->where('default_name', 'not like', "%{$role}%");
                }
            }

            $nUsers = $nUsers->groupBy('name')->get();
            
            return apiResponse($nUsers);
        }
    }

    public function files(Request $request, User $user)
    {
        $keywords = getFTS($request->q);
        
        $perPage = resolvePerPage($request);

        $files = $user->fetchFiles($keywords);
        if (!$files) {
            $files = [];
        } elseif (isset($files['file_name'])) {
            $files = [$files];
        }

        $from = Carbon::now()->toDateString();
        $to = Carbon::now()->addDays(User::HR_ALERT_DAYS)->toDateString();
        $types = UserFileMeta::types();
        $metaByKey = $user->fileMeta->keyBy(fn ($m) => $m->file_name.'.'.$m->file_extension);

        $files = collect($files)->map(function ($file) use ($metaByKey, $from, $to, $types) {
            $key = ($file['file_name'] ?? '').'.'.($file['file_extension'] ?? '');
            $meta = $metaByKey[$key] ?? null;
            $expiry = $meta?->expiry_date?->toDateString();
            $file['document_type'] = $meta->document_type ?? 'other';
            $file['document_type_label'] = $types[$file['document_type']] ?? $file['document_type'];
            $file['expiry_date'] = $expiry;
            $file['is_expired'] = $expiry && $expiry < $from;
            $file['is_expiring'] = $expiry && $expiry >= $from && $expiry <= $to;
            return $file;
        });

        if ($request->document_type) {
            $files = $files->filter(fn ($file) => $file['document_type'] == $request->document_type)->values();
        }
        if ($request->expiring == '1' || $request->expiring == 1) {
            $files = $files->filter(fn ($file) => $file['is_expiring'] || $file['is_expired'])->values();
        }

        $paginate = paginate($files->all(), $perPage, $request->page, $request->options);
        
        return apiPaginateResponse($paginate, UserFileResource::collection($paginate->items()));
    }

    public function addFile(Request $request, User $user)
    {
        $request->validate([
            'document_type' => 'nullable|in:id,passport,contract,medical,other',
            'expiry_date' => 'nullable|date',
        ]);

        if($request->hasFile('file')) {

            $file = $request->file('file');
            $baseName = str_replace(' ', '_', getFTS($request->file_name));
            $extension = $file->getClientOriginalExtension();
            $fileName = $baseName.'.'.$extension;
            $files = $user->fetchFiles($baseName);

            if(!$files || (is_array($files) && count($files) == 0)) {

                $user->saveFile($fileName, $file);
                UserFileMeta::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'file_name' => $baseName,
                        'file_extension' => $extension,
                    ],
                    [
                        'document_type' => $request->document_type ?: 'other',
                        'expiry_date' => $request->expiry_date ?: null,
                    ]
                );
            }
            else {
                return response()->json(['errors' => ['error' => [__('validation.There is already a file with the same name')]]], 422);
            }
            Log::add('add_file', $user, $user->id, '');
        }
        return response()->json(['message' => 'Added successfully.', 'status' => true], 200);
    }

    public function updateFile(Request $request, User $user)
    {
        $input = $request->validate([
            'file_name' => 'required|string',
            'file_extension' => 'required|string',
            'document_type' => 'nullable|in:id,passport,contract,medical,other',
            'expiry_date' => 'nullable|date',
        ]);

        UserFileMeta::updateOrCreate(
            [
                'user_id' => $user->id,
                'file_name' => $input['file_name'],
                'file_extension' => $input['file_extension'],
            ],
            [
                'document_type' => $input['document_type'] ?: 'other',
                'expiry_date' => $input['expiry_date'] ?: null,
            ]
        );

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function deleteFile(Request $request, User $user)
    {
        $user->deleteFile($request->file_name, $request->file_extension);
        UserFileMeta::where('user_id', $user->id)
            ->where('file_name', $request->file_name)
            ->where('file_extension', $request->file_extension)
            ->delete();
        Log::add('delete_file', $user, $user->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function import(Request $request)
    {
        if($request->import == 'import_employees') {
            return $this->importEmployees($request);
        }
        else if($request->import == 'import_parents') {
            return $this->importParents($request);
        }
    }

    public function importEmployees(Request $request)
    {
        if($request->action == 'preview') {

            $data = [];
            $errors = [];
            $nErrors = 0;
            $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/excel', 'application/vnd.msexcel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            if(isset($_FILES['file']['name']) && in_array($_FILES['file']['type'], $file_mimes)) {

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $excel = $reader->load($_FILES['file']['tmp_name']);
                $sheetCount = $excel->getSheetCount();
                
                $nationalities = array_flip(__('nationalities'));

                for ($i = 0; $i < $sheetCount; $i++) {		
                    
                    $sheet = $excel->getSheet($i);

                    $rows = $sheet->toArray(null, true, true, true);

                    $headers = null;

                    foreach ($rows as $values) {

                        $errors = [];

                        if(empty($values)) continue;
                        if(empty($headers)) {
                            
                            if(in_array('name', $values)) {
                                $data = [];
                                $data['headers'][] = [
                                    'title'=> __("tr.errors"),
                                    'key'=> 'errors'
                                ];
                                foreach ($values as $header) {
                                    $data['headers'][] = [
                                        'title'=> __("tr.{$header}"),
                                        'key'=> $header
                                    ];
                                }
                            }

                            $headers = (object)array_flip($values);
                            continue; 
                        }
                        if(empty($headers->name) || empty($values[$headers->name])) continue;

                        $user = null;
                        if($values[$headers->id_or_residence_number] || $values[$headers->email] || $values[$headers->phone]) {
                            $user = User::when(
                                $values[$headers->id_or_residence_number],
                                fn ($q) => $q->orWhere('id_or_residence_number', $values[$headers->id_or_residence_number])
                            )->when(
                                $values[$headers->email],
                                fn ($q) => $q->orWhere('email', $values[$headers->email])
                            )->when(
                                $values[$headers->phone],
                                fn ($q) => $q->orWhere('phone', $values[$headers->phone])
                            )
                            ->withTrashed()
                            ->first();
                        }

                        if($user) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Already exists");
                        }
                            
                        $user = new User();

                        if(!array_key_exists($values[$headers->nationality], $nationalities)) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Missing nationality value");
                        }

                        $rolesID = [];
                        $roles = explode(',', $values[$headers->roles]);
                        if(is_array($roles)) {
                            foreach ($roles as $roleName) {
                                if($roleName) {
                                    $role = Role::where('name', $roleName)->first();
                                    if(!$role) {
                                        $errors[] = __("tr.Error").": ".__("tr.Errors.Invalid role")." ({$roleName})";
                                    }else {
                                        $rolesID[] = $role->id;
                                    }
                                }
                            }
                        }

                        if(empty($values[$headers->email])) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Empty email");
                        }

                        if(empty($values[$headers->phone])) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Empty phone");
                        }

                        $user->code = $values[$headers->code];
                        $user->name = $values[$headers->name];
                        $user->email = $values[$headers->email];
                        $user->nationality = $values[$headers->nationality];
                        $user->nationality_code = isset($nationalities[$values[$headers->nationality]]) ? $nationalities[$values[$headers->nationality]] : null;
                        $user->id_or_residence_number = $values[$headers->id_or_residence_number];
                        $user->phone = $values[$headers->phone];
                        $user->address_unit = $values[$headers->address_unit];
                        $user->address_building = $values[$headers->address_building];
                        $user->address_street = $values[$headers->address_street];
                        $user->address_area = $values[$headers->address_area];
                        $user->address_city = $values[$headers->address_city];
                        $user->address_zipcode = $values[$headers->address_zipcode];
                        $user->address_number = $values[$headers->address_number];

                        $user->roles_id = $rolesID;
                        $user->roles = $values[$headers->roles];
                        $user->errors = $errors;

                        if(count($errors)>0)
                            $nErrors++;
                        
                        $data['records'][] = $user;
                    }
                }
            }

            $data['n_errors'] = $nErrors;
            return apiResponse($data);
        }
        else {

            foreach ($request->data as $record) {

                if(isset($record['id']) && $record['id']>0)
                    $user = User::where('id', $record['id'])->withTrashed()->first();
                else {
                    $user = new User();
                    $user->password = bcrypt('P@ssw0rd');
                }

                $user->name = $record['name'];
                $user->email = $record['email'];	    	
                $user->nationality = isset($record['nationality_code']) ? $record['nationality_code'] : null;
                $user->id_or_residence_number = isset($record['id_or_residence_number']) ? $record['id_or_residence_number'] : null;
                $user->phone = isset($record['phone']) ? $record['phone'] : null;
                $user->address_unit = isset($record['address_unit']) ? $record['address_unit'] : null;
                $user->address_building = isset($record['address_building']) ? $record['address_building'] : null;
                $user->address_street = isset($record['address_street']) ? $record['address_street'] : null;
                $user->address_area = isset($record['address_area']) ? $record['address_area'] : null;
                $user->address_city = isset($record['address_city']) ? $record['address_city'] : null;
                $user->address_zipcode = isset($record['address_zipcode']) ? $record['address_zipcode'] : null;
                $user->address_number = isset($record['address_number']) ? $record['address_number'] : null;
                $user->save();

                if(!$user->isInCenter($request->center_id))
                    CenterUser::create(['center_id'=> $request->center_id, 'user_id'=> $user->id]);

                if(isset($record['roles_id'])) {
                    if(is_array($record['roles_id']) && count($record['roles_id'])>0) {
                        $user->syncRoles($record['roles_id']);
                    }
                }
            }
        
            return response()->json(['message' => 'saved_successfully', 'status' => true], 200);
        }
    }

    public function importParents(Request $request)
    {
        if($request->action == 'preview') {

            $data = [];
            $errors = [];
            $nErrors = 0;
            $file_mimes = array('text/x-comma-separated-values', 'text/comma-separated-values', 'application/octet-stream', 'application/vnd.ms-excel', 'application/excel', 'application/vnd.msexcel', 'text/plain', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            if(isset($_FILES['file']['name']) && in_array($_FILES['file']['type'], $file_mimes)) {

                $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
                $excel = $reader->load($_FILES['file']['tmp_name']);
                $sheetCount = $excel->getSheetCount();

                $nationalities = array_flip(__('nationalities'));
            
                for ($i = 0; $i < $sheetCount; $i++) {		
                    
                    $sheet = $excel->getSheet($i);
            
                    $rows = $sheet->toArray(null, true, true, true);
            
                    $headers = null;
            
                    foreach ($rows as $values) {
                        
                        $errors = [];
            
                        if(empty($values)) continue;
                        if(empty($headers)) {

                            if(in_array('name', $values)) {
                                $data = [];
                                $data['headers'][] = [
                                    'title'=> __("tr.errors"),
                                    'key'=> 'errors'
                                ];
                                foreach ($values as $header) {
                                    $data['headers'][] = [
                                        'title'=> __("tr.{$header}"),
                                        'key'=> $header
                                    ];
                                }
                            }

                            $headers = (object)array_flip($values);
                            continue; 
                        }
                        if(empty($headers->name) || empty($values[$headers->name])) continue;
            
                        $user = null;
                        if($values[$headers->id_or_residence_number] || $values[$headers->email] || $values[$headers->phone]) {
                            $user = User::when(
                                $values[$headers->id_or_residence_number],
                                fn ($q) => $q->orWhere('id_or_residence_number', $values[$headers->id_or_residence_number])
                            )->when(
                                $values[$headers->email],
                                fn ($q) => $q->orWhere('email', $values[$headers->email])
                            )->when(
                                $values[$headers->phone],
                                fn ($q) => $q->orWhere('phone', $values[$headers->phone])
                            )
                            ->withTrashed()
                            ->first();
                        }
            
                        if($user) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Already exists");
                        }
                            
                        $user = new User();
            
                        if(!array_key_exists(trim($values[$headers->nationality]), $nationalities)) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Missing nationality value");
                        }

                        if(empty($values[$headers->phone])) {
                            $errors[] = __("tr.Error").": ".__("tr.Errors.Empty phone");
                        }
                        
                        $user->code = $values[$headers->code];
                        $user->name = $values[$headers->name];
                        $user->email = $values[$headers->email];
                        $user->nationality = trim($values[$headers->nationality]);
                        $user->nationality_code = isset($nationalities[$values[$headers->nationality]]) ? $nationalities[$values[$headers->nationality]] : '';
                        $user->id_or_residence_number = $values[$headers->id_or_residence_number];
                        $user->phone = $values[$headers->phone];
                        $user->address_unit = $values[$headers->address_unit];
                        $user->address_building = $values[$headers->address_building];
                        $user->address_street = $values[$headers->address_street];
                        $user->address_area = $values[$headers->address_area];
                        $user->address_city = $values[$headers->address_city];
                        $user->address_zipcode = $values[$headers->address_zipcode];
                        $user->address_number = $values[$headers->address_number];

                        $user->errors = $errors;

                        if(count($errors)>0)
                            $nErrors++;
                        
                        $data['records'][] = $user;
                    }
                }
            }

            $data['n_errors'] = $nErrors;
            return apiResponse($data);
        }
        else {

            foreach ($request->data as $record) {

                if(isset($record['id']) && $record['id']>0)
                    $user = User::where('id', $record['id'])->withTrashed()->first();
                else {
                    $user = new User();
                    $user->password = bcrypt('P@ssw0rd');

                    if(isset($record['email']) && $record['email']) {
                        $emailExists = User::where('email', $record['email'])->withTrashed()->first();
                        if($emailExists)
                            continue;
                    }

                    if(isset($record['phone']) && $record['phone']) {
                        $phoneExists = User::where('phone', $record['phone'])->withTrashed()->first();
                        if($phoneExists)
                            continue;
                    }
                }

                $user->name = $record['name'];
                $user->email = $record['email'];	    	
                $user->nationality = isset($record['nationality_code']) ? $record['nationality_code'] : null;
                $user->id_or_residence_number = isset($record['id_or_residence_number']) ? $record['id_or_residence_number'] : null;
                $user->phone = isset($record['phone']) ? $record['phone'] : null;
                $user->address_unit = isset($record['address_unit']) ? $record['address_unit'] : null;
                $user->address_building = isset($record['address_building']) ? $record['address_building'] : null;
                $user->address_street = isset($record['address_street']) ? $record['address_street'] : null;
                $user->address_area = isset($record['address_area']) ? $record['address_area'] : null;
                $user->address_city = isset($record['address_city']) ? $record['address_city'] : null;
                $user->address_zipcode = isset($record['address_zipcode']) ? $record['address_zipcode'] : null;
                $user->address_number = isset($record['address_number']) ? $record['address_number'] : null;
                $user->save();
                $user->syncRoles([2]);

                if(!$user->isInCenter($request->center_id))
                    CenterUser::create(['center_id'=> $request->center_id, 'user_id'=> $user->id]);
            }
        
            return response()->json(['message' => 'saved_successfully', 'status' => true], 200);
        }
    }

    public function uploadFile(User $user, Request $request) {
            
        return [$request->all(), $request->file('personal_photo')];

        if ($request->file('file')) {

            $folder = $user->archive;
            $file = $user->personalPhoto();
            if($file) $file->delete();
            $file = $folder->addFile($request->upload_file, "personal_photo");
            $file->rename("personal_photo");

            $user->archive_id = $folder->id;
            $user->save();
        }

        return 'Out';
    }
}