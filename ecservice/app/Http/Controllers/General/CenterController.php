<?php

namespace App\Http\Controllers\General;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Center;
use App\Models\CenterPackage;
use App\Models\CenterPayment;
use App\Models\CenterUser;
use App\Models\User;
use App\Models\SCase;
use App\Models\Message;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\CenterResource;
use App\Http\Resources\CenterPackageResource;
use App\Http\Resources\CenterPaymentResource;
use App\Http\Resources\CenterUserResource;
use App\Http\Resources\Admin\User\UserResource;
use App\Http\Requests\CenterRequest;
use App\Models\System\System;
use PhpParser\Node\Stmt\TryCatch;
use App\Models\Log;
use Carbon\Carbon;
use App\Models\System\PDF;
use App\Support\ReferenceCache;

class CenterController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $perPage = resolvePerPage($request);

        $centers = Center::with(['package'])
        ->when(
            $request->q,
            fn ($q) => $q->where('title', $request->q)
        )
        ->when(
            $request->id,
            fn ($q) => $q->where('centers.id', $request->id)
        )
        ->when(
            $request->user_id,
            fn ($q) => $q->whereHas('users', function ($subQuery) use($request) {
                $subQuery->where('centers_users.user_id', $request->user_id);
            })
        )
        ->when(
            $request->package_id,
            fn ($q) => $q->where('package_id', $request->package_id)
        )
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->when(
            $request->status && $request->status == 'active',
            fn ($q) => $q->where('status', 1)
        )
        ->when(
            $request->status && $request->status == 'new',
            fn ($q) => $q->where('status', 0)
        );

        if($request->export == 'pdf') {

            $dateNow = Carbon::now()->toDateString();
            $center = $centers->first();

            $fileName = "Center {$dateNow}.pdf";
            $bladePath = 'centers.center';
            $data = [
                'center' => new CenterResource($center),
                'payments' => $center->payments,
                'statistics' => $center->statistics(),
                'storage_size' => $center->storageSize(),
                'n_cases' => $center->cases()->count(),
                'n_staff' => $center->centerUsers()->whereHas('user.roles', function ($query) {
                    $query->where('default_name', '!=', 'parent');
                    $query->orWhereNull('default_name');
                })->count(),
                'date' => $dateNow,
                'platform_logo' => $center->platformURLLogo()['file_url']
            ];

            if($fileName && $bladePath && $data) {

                $token = PDF::savePdf($bladePath, $data, $fileName);
                return success(['url'=> route('file.download', ['token'=> $token])]);
            }
        }

        $centers = $centers->paginate($perPage);
        return apiPaginateResponse($centers, CenterResource::listCollection($centers));
    }
    
    public function filterItems(Request $request){

        $payload = ReferenceCache::remember('centers', 'items:active:slim:'.app()->getLocale(), function () {
            $centers = Center::query()
                ->where('status', 1)
                ->orderBy('title')
                ->get(['id', 'title', 'title_local']);

            return $centers->map(fn ($center) => [
                'id' => $center->id,
                'title' => $center->getNameAttribute(),
            ])->values()->all();
        });

        return apiResponse($payload);
    }

    public function show(Request $request, $id) {

        $center = Center::find($id);
        if($request->details && $request->details == 'all') {

            $nCases = $center->cases()->count();
            $nStaff = $center->centerUsers()->whereHas('user.roles', function ($query) {
                $query->where('default_name', '!=', 'parent');
                $query->orWhereNull('default_name');
            })->count();

            $managers = $center->managers();
            $center->title = $center->getNameAttribute();
            $center->package_name = $center->package->title;
            $center->managers = array_values($managers);
            $center->managers_id = array_values(array_flip($managers));
            $center->logo = $center->urlLogo();
            $center->n_cases = $nCases;
            $center->n_staff = $nStaff;
            $center->storage_size = $center->storageSize();
            $center->can_pay = $center->canPay();
            $center->is_paid = $center->isPaid();
            $center->next_subscription_date = $center->nextSubscription();

            $statistics = $center->statistics();
            $center->goals_daily = $statistics->goals_daily;
            $center->goals_monthly = $statistics->goals_monthly;
            $center->whats_app_daily = $statistics->whats_app_daily;
            $center->whats_app_monthly = $statistics->whats_app_monthly;
            $center->whats_app_messages = $statistics->whats_app_messages;

            return apiResponse($center);
        }

        return apiResponse(new CenterResource($center));
    }

    public function calculatePayment(Request $request, $id) {

        $center = Center::find($id);
        return apiResponse($center->paymentAfterDiscount($request));
    }

    public function put(CenterRequest $request) {

        $input = $request->validated();
        $type = 'add_center';
        $message = 'Added successfully.';
        if($request->id>0) {
            $type = 'edit_center';
            $message = 'Updated successfully.';
        }

        $center = Center::updateOrCreate(['id' => $request->id], $input);

        if($request->id>0) {

            $centerManagers = User::select('users.*')
            ->whereHas('centers', function ($query) use ($center) {
                $query->where('centers.id', $center->id);
            })
            ->whereHas('roles', function ($query) {
                $query->where('default_name', 'manager');
            })
            ->get();
            foreach ($centerManagers as $centerManager) {
                $centerManager->removeRole(3);
            }

            if($request->managers) {

                $managersIDs = explode(',', $request->managers);
                $managers = User::whereIn('id', $managersIDs)->get();
                foreach ($managers as $manager) {

                    if(isHasRole('admin', $manager) && !$manager->isInCenter($manager->id))
                        CenterUser::create(['center_id'=> $manager->id, 'user_id'=> $manager->id]);

                    $managerRoles = $manager->roles()->pluck('roles.id')->toArray();
                    $managerRoles[] = 3;
                    $manager->syncRoles($managerRoles);
                }
            }
        }
        else  {

            $userInput = array(
                'name'=> $request->manager_name,
                'phone'=> $request->manager_phone,
                'email'=> $request->manager_email,
                'password'=> $request->manager_password
            );

            $user = User::create($userInput);
            CenterUser::create(['center_id'=> $center->id, 'user_id'=> $user->id]);
            $user->syncRoles([3]);
        }

        if($request->hasFile('logo')) {
            $currentLogo = null;
            if($request->current_logo && !str_contains($request->current_logo, 'centers/motabaah.png'))
                $currentLogo = $request->current_logo;
            $center->setLogo($request->file('logo'), $currentLogo);
        }

        Log::add($type, $center, $center->id, '');
        ReferenceCache::bump('centers');
        return response()->json(['id' => $center->id, 'message' => $message, 'status' => true]);
    }

    public function activate($id) {

        $center = Center::find($id);
        $center->status = 1;
        $center->save();
        ReferenceCache::bump('centers');
        return success();
    }

    public function delete($id) {

        $record = Center::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_center', $record, $record->id, '');
        ReferenceCache::bump('centers');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function deleteManager($id) {

        $record = Center::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }

        $managers = User::whereHas('centers', function ($query) use($record) {
            $query->where('centers.id', $record->id);
        })
        ->whereHas('roles', function ($query) {
            $query->where('default_name', 'manager');
        })
        ->get();

        foreach ($managers as $manager) {
            $manager->removeRole(3);
        }

        Log::add('delete_center_manager', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function deleteLogo($id) {

        $record = Center::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->deleteLogo();
        Log::add('delete_center_logo', $record, $record->id, '');
        ReferenceCache::bump('centers');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restore($id)
    {
        $record = Center::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_center', $record, $record->id, '');
        ReferenceCache::bump('centers');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function packages(Request $request)
    {
        $payload = ReferenceCache::remember('packages', 'all:'.app()->getLocale(), function () {
            $packages = CenterPackage::get();

            return ReferenceCache::payload(CenterPackageResource::collection($packages));
        }, 300);

        return apiResponse($payload);
    }

    public function payments(Request $request)
    {
        $user = auth()->user();
        $perPage = resolvePerPage($request);

        $payments = CenterPayment::when(
            $request->q,
            fn ($q) => $q->where('title', $request->q)
        )
        ->when(
            $request->center_id,
            fn ($q) => $q->where('center_id', $request->center_id)
        )
        ->when(
            $request->user_id,
            fn ($q) => $q->where('user_id', $request->user_id)
        )
        ->when(
            $request->package_id,
            fn ($q) => $q->where('package_id', $request->package_id)
        )
        ->when(
            $request->user_id,
            fn ($q) => $q->where('user_id', $request->user_id)
        )
        ->where(function ($query) use ($request) {
            if($request->from_date && $request->to_date) {
                $query->orWhereBetween('date', [$request->from_date, $request->to_date]);
            }
            else if($request->from_date) {
                $query->orWhere(function ($query) use ($request) {
                    $query->where('date', '>=', $request->from_date);
                });
            }
            else if($request->to_date) {
                $query->orWhere(function ($query) use ($request) {
                    $query->where('date', '>=', $request->to_date);
                });
            }
        })
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->orderBy('expiry_date', 'DESC')
        ->orderBy('created_at', 'DESC');
        
        if($request->pdf == 'payments') {
            
            $payments = $payments->get();
            $fileName = 'Payments '.Carbon::now().'.pdf';
            $pdfData = [
                'center' => Center::find($request->center_id),
                'items' => $payments
            ];
            $token = PDF::savePdf('centers.payments', $pdfData, $fileName);
            return success(['url'=> route('file.download', ['token'=> $token])]);
        }
        else {

            $payments = $payments->paginate($perPage);
            return apiPaginateResponse($payments, CenterPaymentResource::collection($payments));
        }
    }

    public function putPayment(Request $request) {

        $center = Center::find($request->center_id);
        if($request['amount'] > 0) {

            $request['status'] = 1;
            $request['user_id'] = auth()->user()->id;
            $request['date'] = Carbon::now();

            if($request['payment_type'] == CenterPayment::PAYMENT_TYPE_MONTHLY) {
                $request['expiry_date'] = (clone $request['date'])->addMonths($request['payment_duration']);
            }
            else if($request['payment_type'] == CenterPayment::PAYMENT_TYPE_YEARLY) {
                $request['expiry_date'] = (clone $request['date'])->addYears($request['payment_duration']);
            }

            $now = Carbon::now();
            $lastPayment = $center->payments()->orderBy('expiry_date', 'DESC')->orderBy('created_at', 'ASC')->first();
            if($lastPayment && $now->between($lastPayment->date, $lastPayment->expiry_date)) {
                $request['date'] = $lastPayment->date;
                $request['expiry_date'] = $lastPayment->expiry_date;

                if($center->package_id ==  $request->package_id) {
                    if($request['payment_type'] == CenterPayment::PAYMENT_TYPE_MONTHLY) {
                        $request['expiry_date'] = (clone Carbon::parse($request['expiry_date']))->addMonths($request['payment_duration']);
                    }
                    else if($request['payment_type'] == CenterPayment::PAYMENT_TYPE_YEARLY) {
                        $request['expiry_date'] = (clone Carbon::parse($request['expiry_date']))->addYears($request['payment_duration']);
                    }
                }
            }

            $payment = CenterPayment::updateOrCreate(['id'=> $request['id']], $request->all());
        }
        $center->package_id =  $request->package_id;
        $center->number_of_cases =  $request->number_of_cases;
        $center->save();
        return response()->json(['center' => new CenterResource($center), 'status' => true]);
    }

    public function actionPayment(Request $request, CenterPayment $payment) {

        if($request->action == 'pay') {

            $payment->name = $request->name;
            $payment->email = $request->email;
            $payment->phone_number = $request->phone;
            $payment->status = CenterPayment::STATUS_PAID;
            $payment->paid_by = auth()->user()->id;
            if($request->hasFile('file'))
                $payment->saveFile($request->file('file'));
        }
        else if($request->action == 'unpay') {

            $payment->status = CenterPayment::STATUS_UNPAID;
            $payment->paid_by = null;
            $payment->deleteFile();
        }

        $payment->save();

        Log::add($request->action.'_center_payment', $payment, $payment->id, '');

        return response()->json(['message' => 'Updated successfully.', 'status' => true], 200);
    }

    public function deletePayment($id)
    {
        $record = CenterPayment::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_center_payment', $record, $record->id, '');
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }

    public function restorePayment($id)
    {
        $record = CenterPayment::withTrashed()->find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_center_payment', $record, $record->id, '');
        return response()->json(['message' => 'Record restored successfully', 'status' => true]);
    }

    public function users(Request $request)
    {
        $user = auth()->user();
        $perPage = resolvePerPage($request);

        $keywords = mb_ereg_replace(" ", "%", getFTS($request->q));

        $users = User::with(['roles:id,name,default_name', 'centers:id,title,title_local'])
        ->when(
            $keywords,
            fn ($q) => $q->where('search_text', 'like',"%{$keywords}%")
        )
        ->when(
            $request->center,
            fn ($q) => $q->whereHas('centers', function ($query) use ($request) {
                $query->where('centers.id', $request->center);
            })
        )
        ->when(
            $request->user_id,
            fn ($q) => $q->whereHas('users', function ($subQuery) use($request) {
                $subQuery->where('centers_users.user_id', $request->user_id);
            })
        )
        ->when(
            $request->roles,
            fn ($q) => $q->whereHas('roles', function ($query) use ($request) {
                $query->whereIn('roles.id', $request->roles);
            })
        )
        ->when(
            $request->status && $request->status == 'all',
            fn ($q) => $q->withTrashed()
        )
        ->when(
            $request->status && $request->status == 'inactive',
            fn ($q) => $q->onlyTrashed()
        )
        ->paginate($perPage);

        return apiPaginateResponse($users, UserResource::listCollection($users));
    }

    public function putUser(Request $request) {

        $centerUser = CenterUser::updateOrCreate(['id' => $request->id], $request->all());
        $user = User::find($centerUser->user_id);
        return success(new UserResource($user));
    }

    public function deleteUser($id)
    {
        $record = CenterUser::find($id);
        if (!$record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        Log::add('delete_center_user', $record, $record->id, '');
        $record->delete();
        return response()->json(['message' => 'Record deleted successfully', 'status' => true]);
    }
}
