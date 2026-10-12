<?php

namespace App\Http\Controllers\General;

use App\Http\Controllers\Controller;
use App\Http\Requests\MeetingRoomRequest;
use App\Http\Resources\MeetingRoomsResource;
use App\Models\Log;
use App\Models\MeetingRoom;
use App\Models\Message;
use App\Models\User;
use App\Models\System\System;
use App\Support\MeetingRoomAccess;
use App\Support\MeetingRoomNotifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MeetingRoomController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        $isAdminMeetings = $user->can('admin_meetings');
        $parent = isHasRole(System::USER_TYPE_PARENT_ROLE_NAME);
        $specialistTeacherIds = [];
        $keyword = mb_ereg_replace(' ', '%', getFTS($request->q));

        $center = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        } elseif ($user->centers) {
            $center = $user->centers[0]->id;
        }

        if ($parent) {
            $user->load(['scaseParent.teacher:id', 'scaseParent.specialists:id']);
            $teacherIds = $user->scaseParent->pluck('teacher.*.id')->flatten()->all();
            $specialistIds = $user->scaseParent->pluck('specialists.*.id')->flatten()->all();
            $specialistTeacherIds = array_merge($teacherIds, $specialistIds);
        }

        $perPage = resolvePerPage($request);

        $rooms = MeetingRoom::with(['user', 'assignedParents:id,name,phone'])
            ->where('meeting_rooms.center_id', $center)
            ->when(
                ! $isAdminMeetings && ! $parent,
                fn ($q) => $q->where(function ($query) use ($user) {
                    $query->where('created_by_id', $user->id);
                    $query->orWhere('type', MeetingRoom::TYPE_ADMINISTRATIVE);
                })
            )
            ->when(
                ! $isAdminMeetings && $parent,
                fn ($q) => $q->where(function ($query) use ($user, $specialistTeacherIds) {
                    $query->where(function ($generalAudience) use ($specialistTeacherIds) {
                        $generalAudience->where(function ($audience) {
                            $audience->whereNull('audience_type')
                                ->orWhere('audience_type', MeetingRoom::AUDIENCE_GENERAL);
                        })
                            ->where(function ($inner) use ($specialistTeacherIds) {
                                $inner->whereIn('created_by_id', $specialistTeacherIds)
                                    ->orWhere('type', MeetingRoom::TYPE_GENERAL);
                            });
                    })
                        ->orWhere(function ($parentSpecific) use ($user) {
                            $parentSpecific->where('audience_type', MeetingRoom::AUDIENCE_PARENTS)
                                ->whereHas('assignedParents', fn ($p) => $p->where('users.id', $user->id));
                        });
                })
            )
            ->when(
                $request->q,
                fn ($q) => $q->where(function ($query) use ($keyword) {
                    $query->where('title', 'like', "%{$keyword}%")
                        ->orWhere('title_local', 'like', "%{$keyword}%");
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

        $rooms = $rooms->paginate($perPage);

        return apiPaginateResponse($rooms, MeetingRoomsResource::collection($rooms));
    }

    public function show(Request $request, $room)
    {
        $user = auth()->user();
        $center = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $center = $request->center_id;
        } elseif ($user->centers) {
            $center = $user->centers[0]->id;
        }

        $room = MeetingRoom::with(['user', 'assignedParents:id,name,phone'])
            ->where('center_id', $center)
            ->withTrashed()
            ->find($room);

        if (! $room || ! MeetingRoomAccess::userCanAccess($user, $room)) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        return apiResponse(new MeetingRoomsResource($room));
    }

    public function put(MeetingRoomRequest $request, $room = null)
    {
        $user = auth()->user();
        if (! $user->can('edit_meetings') && ! $user->can('admin_meetings')) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        $input = $request->validated();
        $input['created_by_id'] = $user->id;
        $input['center_id'] = null;
        if ($request->center_id && $user->isInCenter($request->center_id)) {
            $input['center_id'] = $request->center_id;
        }

        if (! $input['center_id']) {
            return response()->json(['errors' => ['error' => [__("validation.You don't belong to any center")]]], 422);
        }

        $parentIds = array_values(array_map('intval', $input['parent_ids'] ?? []));
        unset($input['parent_ids']);

        if ($input['audience_type'] === MeetingRoom::AUDIENCE_PARENTS) {
            $validation = MeetingRoomAccess::validateParentAssignments((int) $input['center_id'], $parentIds);
            if (! $validation['valid']) {
                return $validation['response'];
            }
        } else {
            $parentIds = [];
        }

        $roomId = (int) $room;
        $existing = $roomId > 0 ? MeetingRoom::where('center_id', $input['center_id'])->find($roomId) : null;
        if ($roomId > 0 && ! $existing) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Room not found')]]], 422);
        }

        $meeting = DB::transaction(function () use ($input, $roomId, $parentIds) {
            $meeting = MeetingRoom::updateOrCreate(['id' => $roomId > 0 ? $roomId : null], $input);
            $meeting->assignedParents()->sync($parentIds);

            if (\Illuminate\Support\Facades\Schema::hasTable('meeting_room_notified_users')) {
                DB::table('meeting_room_notified_users')
                    ->where('meeting_room_id', $meeting->id)
                    ->whereNotIn('user_id', $parentIds ?: [0])
                    ->delete();
            }

            return $meeting->fresh(['assignedParents']);
        });

        $type = $roomId > 0 ? 'edit_meeting' : 'add_meeting';
        Log::add($type, $meeting, $meeting->id, '');

        if ($meeting->audience_type === MeetingRoom::AUDIENCE_PARENTS) {
            MeetingRoomNotifier::syncForRoom($meeting);
        }

        return success();
    }

    public function delete($id)
    {
        $record = MeetingRoom::find($id);
        if (! $record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->delete();
        Log::add('delete_meeting', $record, $record->id, '');

        return success();
    }

    public function restore($id)
    {
        $record = MeetingRoom::withTrashed()->find($id);
        if (! $record) {
            return response()->json(['message' => 'Record not found', 'status' => false], 404);
        }
        $record->restore();
        Log::add('restore_meeting', $record, $record->id, '');

        return success();
    }

    public function start_meeting_meesage(Request $request)
    {
        $user = auth()->user();
        $meetingRoom = MeetingRoom::find($request->meeting_room_id);
        if (! $meetingRoom || ! MeetingRoomAccess::userCanAccess($user, $meetingRoom)) {
            return response()->json(['errors' => ['error' => [__('validation.meeting_rooms.Access denied')]]], 403);
        }

        $message = Message::create([
            'type' => $request->routeIs('meeting.start_now') ? Message::SYS_START_NOW_MEETING : Message::SYS_START_LATER_MEETING,
            'content' => $request->message,
            'meeting_room_id' => $request->meeting_room_id,
            'user_id' => auth()->user()->id,
        ]);

        if (! $request->routeIs('meeting.start_now')) {

            $content = json_decode($request->message, true);
            $day = Carbon::parse($content['date'])->locale('ar')->dayName;

            $whatsAppMessage = 'سوف تكون لدينا محاضرة';
            $whatsAppMessage .= " يوم {$day}";
            $whatsAppMessage .= " {$content['date']}";
            $whatsAppMessage .= " الساعة {$content['time']}";
            $whatsAppMessage .= " يمكن الدخول عن طريق الضغط على زرار 'بدء محادثة أونلاين' او استخدم الرابط التالي:";
            $whatsAppMessage .= " {$content['url']}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        } else {

            $whatsAppMessage = 'بدأ الاجتماع الآن يمكن الدخول عن طريق استخدم الرابط التالي:';
            $whatsAppMessage .= " {$request->message}";
            $url = config('app.front_url')."/meeting-rooms/view/{$request->meeting_room_id}";
        }

        $users = $this->meetingNotificationRecipients($meetingRoom);

        \Log::channel('whatsapp')->info('whatsApp Message');
        \Log::channel('whatsapp')->info('Message: '.$whatsAppMessage);
        \Log::channel('whatsapp')->info('URL: '.$url);

        sendWhatsAppMessage(config('motabaa.dev.phone'), $whatsAppMessage, $url);
        sendWhatsAppMessage(config('motabaa.dev.phone_2'), $whatsAppMessage, $url);

        $usersPhone = [];
        if (! config('app.debug')) {
            foreach ($users as $notifyUser) {
                if ($notifyUser->phone) {
                    $usersPhone[] = $notifyUser->phone;
                    sendWhatsAppMessage($notifyUser->phone, $whatsAppMessage, $url);
                }
            }
        }
        \Log::channel('whatsapp')->info($usersPhone);
        $logData = [];
        $logData['message'] = $whatsAppMessage;
        $logData['url'] = $url;
        $logData['phones'] = implode(', ', $usersPhone);
        Log::add('start_meeting_message', $message, $message->id, json_encode($logData));

        return success();
    }

    /**
     * @return \Illuminate\Support\Collection<int, User>
     */
    private function meetingNotificationRecipients(MeetingRoom $meetingRoom)
    {
        if (MeetingRoomAccess::isParentSpecificAudience($meetingRoom)) {
            return $meetingRoom->assignedParents()->get();
        }

        $roles = [];
        if ($meetingRoom->type == MeetingRoom::TYPE_ADMINISTRATIVE) {
            $roles = ['parent'];
        } else {
            $roles = [
                'teacher',
                'social_specialist',
                'occupational_specialist',
                'pronunciation_speech_specialist',
                'psychotherapist_specialist',
                'physiotherapist_specialist',
            ];
        }

        return User::with('roles:id,name')
            ->whereHas('roles', function ($query) use ($roles) {
                $query->whereIn('default_name', $roles);
            })->get();
    }
}
